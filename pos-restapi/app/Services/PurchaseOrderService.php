<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Order (F9): buat/ubah, terima barang (stok masuk + harga modal rata-rata), batalkan.
 * Stok hanya bertambah lewat StockService saat barang diterima.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
        private readonly DocumentNumber $numbers,
    ) {}

    /** @param array<string, mixed> $data hasil validasi PurchaseOrderRequest */
    public function save(PurchaseOrder $po, array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $user) {
            if ($po->exists) {
                $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
                if (! $po->status->editable()) {
                    throw new BusinessException("PO {$po->number} sudah {$po->status->label()} sehingga tidak bisa diubah.");
                }
            } else {
                $outlet = Outlet::query()->findOrFail($data['outlet_id']);
                $po->fill(['number' => $this->numbers->next('PO', $outlet, PurchaseOrder::class), 'outlet_id' => $outlet->id, 'user_id' => $user->id]);
            }

            $status = PurchaseOrderStatus::from($data['status']);
            $po->fill([
                'supplier_id' => $data['supplier_id'],
                'note' => $data['note'] ?? null,
                'status' => $status,
                'ordered_at' => $status === PurchaseOrderStatus::Ordered ? ($po->ordered_at ?? now()) : null,
                'total' => array_sum(array_map(fn ($i) => $i['quantity'] * $i['cost_price'], $data['items'])),
            ])->save();

            $po->items()->delete();
            $products = Product::withTrashed()->with('variants')->whereKey(array_column($data['items'], 'product_id'))->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $variant = ! empty($item['variant_id']) ? $product->variants->firstWhere('id', (int) $item['variant_id']) : null;
                $po->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $variant ? "{$product->name} - {$variant->name}" : $product->name,
                    'quantity' => $item['quantity'],
                    'cost_price' => $item['cost_price'],
                    'total' => $item['quantity'] * $item['cost_price'],
                ]);
            }

            return $po->load('items', 'supplier', 'outlet');
        });
    }

    /**
     * Terima barang: jumlah per item (default di UI = sisa pesanan). Penerimaan sebagian → status partial.
     *
     * @param  list<array{id: int, quantity: int}>  $received
     */
    public function receive(PurchaseOrder $po, array $received, User $user, ?string $note = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $received, $note) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if (! $po->status->receivable()) {
                throw new BusinessException("PO {$po->number} sudah {$po->status->label()}. Tidak ada barang yang bisa diterima lagi.");
            }

            $items = $po->items()->lockForUpdate()->get()->keyBy('id');
            $quantities = collect($received)->filter(fn ($r) => $r['quantity'] > 0)->mapWithKeys(fn ($r) => [(int) $r['id'] => (int) $r['quantity']]);
            $this->validateQuantities($items, $quantities);

            $changes = $quantities->map(fn ($qty, $id) => new StockChange($items[$id]->product_id, $qty, $items[$id]->variant_id, $items[$id]->cost_price))->values()->all();
            $this->stock->apply($po->outlet_id, $changes, StockMovementType::Purchase, $po, $note ?: "Terima barang {$po->number}");

            $costChanges = [];
            foreach ($quantities->sortBy(fn ($q, $id) => [$items[$id]->product_id, $items[$id]->variant_id ?? 0]) as $id => $qty) {
                $item = $items[$id];
                $item->increment('received_quantity', $qty);
                if ($change = $this->updateAverageCost($item, $qty)) {
                    $costChanges[] = $change;
                }
            }

            $complete = $items->every(fn (PurchaseOrderItem $i) => $i->received_quantity >= $i->quantity);
            $po->update([
                'status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::Partial,
                'received_at' => now(),
                'ordered_at' => $po->ordered_at ?? now(),
            ]);

            $this->audit->log('purchase_order.received', $po, null, [
                'items' => $quantities->all(),
                'cost_changes' => $costChanges,
                'status' => $po->status->value,
            ]);

            return $po->load('items', 'supplier', 'outlet');
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if (! $po->status->editable()) {
                throw new BusinessException("PO {$po->number} sudah {$po->status->label()} sehingga tidak bisa dibatalkan.");
            }
            $before = ['status' => $po->status->value];
            $po->update(['status' => PurchaseOrderStatus::Cancelled]);
            $this->audit->log('purchase_order.cancelled', $po, $before, ['status' => $po->status->value]);

            return $po->load('items', 'supplier', 'outlet');
        });
    }

    private function validateQuantities($items, $quantities): void
    {
        if ($quantities->isEmpty()) {
            throw new BusinessException('Isi jumlah diterima minimal untuk satu produk.', 422, ['errors' => ['items' => ['Isi jumlah diterima minimal untuk satu produk.']]]);
        }
        $errors = [];
        foreach ($quantities as $id => $qty) {
            $item = $items->get($id);
            if (! $item) {
                $errors['items'] = ['Ada produk yang bukan bagian dari PO ini. Muat ulang halaman.'];
            } elseif ($qty > $item->remaining()) {
                $errors["items.{$id}"] = ["{$item->product_name}: maksimal {$item->remaining()} lagi (dipesan {$item->quantity}, sudah diterima {$item->received_quantity})."];
            }
        }
        if ($errors) {
            throw new BusinessException('Jumlah diterima melebihi sisa pesanan.', 422, ['errors' => $errors]);
        }
    }

    /**
     * Harga modal rata-rata tertimbang (F9.2): (stok lama × modal lama + diterima × harga beli) / stok baru.
     * Stok lama = total semua outlet sebelum penerimaan ini (negatif dianggap 0).
     *
     * @return array{name: string, before: int, after: int}|null
     */
    private function updateAverageCost(PurchaseOrderItem $item, int $received): ?array
    {
        $target = $item->variant_id
            ? ProductVariant::withTrashed()->lockForUpdate()->find($item->variant_id)
            : Product::withTrashed()->lockForUpdate()->find($item->product_id);
        if (! $target) {
            return null;
        }

        $onHandAfter = (int) ProductStock::query()
            ->where('product_id', $item->product_id)
            ->when($item->variant_id, fn ($q) => $q->where('variant_id', $item->variant_id), fn ($q) => $q->whereNull('variant_id'))
            ->sum('quantity');
        $before = max(0, $onHandAfter - $received);
        $total = $before + $received;
        $old = (int) $target->cost_price;
        $new = intdiv(($before * $old + $received * $item->cost_price) * 2 + $total, $total * 2);

        if ($new === $old) {
            return null;
        }
        $target->update(['cost_price' => $new]);

        return ['name' => $item->product_name, 'before' => $old, 'after' => $new];
    }
}
