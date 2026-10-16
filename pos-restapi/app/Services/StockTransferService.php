<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transfer stok (F10): buat → stok asal berkurang (in_transit) → outlet tujuan menerima → stok tujuan bertambah.
 * Selisih kirim vs terima dicatat sebagai hilang di perjalanan. Semua perubahan stok lewat StockService.
 */
class StockTransferService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array{from_outlet_id: int, to_outlet_id: int, note?: ?string, items: list<array{product_id: int, variant_id?: ?int, quantity: int}>} $data */
    public function create(array $data, User $user): StockTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $from = Outlet::query()->findOrFail($data['from_outlet_id']);
            $transfer = StockTransfer::query()->create([
                'number' => $this->numbers->next('TRF', $from, StockTransfer::class),
                'from_outlet_id' => $from->id,
                'to_outlet_id' => $data['to_outlet_id'],
                'status' => StockTransfer::IN_TRANSIT,
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);

            $products = Product::query()->with('variants')->whereKey(array_column($data['items'], 'product_id'))->get()->keyBy('id');
            $changes = [];
            foreach ($data['items'] as $row) {
                $product = $products[$row['product_id']];
                $variant = ! empty($row['variant_id']) ? $product->variants->firstWhere('id', (int) $row['variant_id']) : null;
                $item = $transfer->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $variant ? "{$product->name} - {$variant->name}" : $product->name,
                    'quantity' => $row['quantity'],
                    'cost_price' => $variant ? ($variant->cost_price ?: $product->cost_price) : $product->cost_price,
                ]);
                $changes[] = new StockChange($item->product_id, -$item->quantity, $item->variant_id, $item->cost_price);
            }

            // Stok kurang → InsufficientStockException (409) dan seluruh transfer dibatalkan
            $this->stock->apply($from->id, $changes, StockMovementType::TransferOut, $transfer, "Transfer {$transfer->number} ke {$transfer->toOutlet->code}");
            $this->audit->log('stock_transfer.created', $transfer, null, ['to_outlet_id' => $transfer->to_outlet_id, 'items' => count($changes)]);

            return $transfer;
        });
    }

    /** @param list<array{id: int, quantity: int}> $received jumlah diterima per item (default di UI = jumlah dikirim) */
    public function receive(StockTransfer $transfer, array $received, User $user, ?string $note): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $received, $user, $note) {
            $transfer = $this->lockInTransit($transfer);
            $items = $transfer->items()->lockForUpdate()->get()->keyBy('id');
            $quantities = collect($received)->mapWithKeys(fn ($r) => [(int) $r['id'] => (int) $r['quantity']]);

            $errors = [];
            foreach ($items as $id => $item) {
                $qty = $quantities[$id] ?? $item->quantity;
                if ($qty > $item->quantity) {
                    $errors["items.{$id}"] = ["{$item->product_name}: maksimal {$item->quantity} sesuai jumlah dikirim."];
                }
            }
            if ($errors) {
                throw new BusinessException('Jumlah diterima melebihi jumlah dikirim.', 422, ['errors' => $errors]);
            }

            $changes = [];
            $missing = [];
            foreach ($items as $id => $item) {
                $qty = $quantities[$id] ?? $item->quantity;
                $item->update(['received_quantity' => $qty]);
                if ($qty > 0) {
                    $changes[] = new StockChange($item->product_id, $qty, $item->variant_id, $item->cost_price);
                }
                if ($qty < $item->quantity) {
                    $missing[] = ['name' => $item->product_name, 'sent' => $item->quantity, 'received' => $qty];
                }
            }
            if ($missing && blank($note)) {
                throw new BusinessException('Jumlah diterima kurang dari yang dikirim. Tulis catatan penyebabnya, mis. rusak di perjalanan.', 422, ['errors' => ['note' => ['Wajib diisi bila jumlah diterima kurang dari yang dikirim.']]]);
            }

            if ($changes) {
                $this->stock->apply($transfer->to_outlet_id, $changes, StockMovementType::TransferIn, $transfer, "Terima transfer {$transfer->number} dari {$transfer->fromOutlet->code}");
            }
            $transfer->update(['status' => StockTransfer::RECEIVED, 'received_by' => $user->id, 'received_at' => now(), 'receive_note' => $note]);
            $this->audit->log('stock_transfer.received', $transfer, null, ['missing' => $missing]);

            return $transfer;
        });
    }

    /** Batal selama masih di perjalanan: stok kembali ke outlet asal. */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            $transfer = $this->lockInTransit($transfer);
            $changes = $transfer->items()->get()->map(fn (StockTransferItem $i) => new StockChange($i->product_id, $i->quantity, $i->variant_id, $i->cost_price))->all();
            $this->stock->apply($transfer->from_outlet_id, $changes, StockMovementType::TransferIn, $transfer, "Pembatalan transfer {$transfer->number}");
            $transfer->update(['status' => StockTransfer::CANCELLED]);
            $this->audit->log('stock_transfer.cancelled', $transfer, ['status' => StockTransfer::IN_TRANSIT], ['status' => StockTransfer::CANCELLED]);

            return $transfer;
        });
    }

    private function lockInTransit(StockTransfer $transfer): StockTransfer
    {
        $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
        if ($transfer->status !== StockTransfer::IN_TRANSIT) {
            throw new BusinessException("Transfer {$transfer->number} sudah ".strtolower(StockTransfer::STATUS_LABELS[$transfer->status]).'.');
        }

        return $transfer;
    }
}
