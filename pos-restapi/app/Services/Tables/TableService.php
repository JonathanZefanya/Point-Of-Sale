<?php

namespace App\Services\Tables;

use App\Enums\SaleStatus;
use App\Exceptions\BusinessException;
use App\Models\DiningTable;
use App\Models\Sale;
use App\Models\User;
use App\Services\KitchenService;
use App\Services\Sales\SaleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Meja & bill meja (3.3): kelola meja, pindah bill ke meja lain, gabung dua bill. */
class TableService
{
    public function __construct(
        private readonly TableOccupancy $occupancy,
        private readonly SaleService $sales,
        private readonly KitchenService $kitchen,
    ) {}

    /** Meja outlet + bill terbuka di tiap meja (untuk denah di kasir). */
    public function forOutlet(int $outletId, bool $activeOnly = false): Collection
    {
        return DiningTable::query()->where('outlet_id', $outletId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->with(['openSale' => fn ($q) => $q->withCount('items')])
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function save(DiningTable $table, array $data): DiningTable
    {
        $table->fill($data);
        if (! $table->exists && ! isset($data['sort_order'])) {
            $table->sort_order = (int) DiningTable::query()->where('outlet_id', $table->outlet_id)->max('sort_order') + 1;
        }
        $table->save();

        return $table->load('openSale');
    }

    public function delete(DiningTable $table): void
    {
        if ($table->openSale()->exists()) {
            throw new BusinessException("Meja {$table->name} masih punya bill terbuka. Selesaikan atau pindahkan bill-nya dulu.");
        }
        $table->delete();
    }

    /** Pindahkan bill ke meja lain yang kosong. */
    public function move(Sale $sale, int $tableId): Sale
    {
        return DB::transaction(function () use ($sale, $tableId) {
            $sale = $this->lockHeld($sale);
            $table = $this->occupancy->lockFree($tableId, $sale->outlet_id, $sale->id);
            $sale->update(['table_id' => $table->id, 'table_name' => $table->name, 'order_type' => 'dine_in']);

            return $sale->load(['items', 'user', 'customer']);
        });
    }

    /**
     * Gabung bill $source ke bill $target (mis. dua meja jadi satu rombongan).
     * Item digabung lalu total dihitung ulang; bill asal dihapus (masih draf, belum ada uang/stok).
     */
    public function merge(Sale $source, int $targetId, User $user): Sale
    {
        return DB::transaction(function () use ($source, $targetId, $user) {
            if ($source->id === $targetId) {
                throw new BusinessException('Pilih bill lain untuk digabungkan.', 422);
            }
            // Kunci berurutan menurut id agar dua penggabungan bersamaan tidak deadlock
            [$first, $second] = $source->id < $targetId ? [$source->id, $targetId] : [$targetId, $source->id];
            $locked = [$first => $this->lockHeld(Sale::query()->findOrFail($first)), $second => $this->lockHeld(Sale::query()->findOrFail($second))];
            [$source, $target] = [$locked[$source->id], $locked[$targetId]];
            if ($source->outlet_id !== $target->outlet_id) {
                throw new BusinessException('Bill hanya bisa digabung dengan bill di outlet yang sama.', 422);
            }

            $items = $target->items()->get()->concat($source->items()->get())->map(fn ($i) => [
                'product_id' => $i->product_id,
                'variant_id' => $i->variant_id,
                'modifier_ids' => array_column($i->modifiers_json ?? [], 'id'),
                'quantity' => $i->quantity,
                'note' => $i->note,
            ])->all();

            $this->kitchen->mergeSent($target, $source); // item bill asal sudah di dapur
            $merged = $this->sales->hold($user, [
                'outlet_id' => $target->outlet_id,
                'items' => $items,
                'held_sale_id' => $target->id,
                'hold_label' => $target->hold_label,
                'customer_id' => $target->customer_id ?? $source->customer_id,
                'table_id' => $target->table_id,
                'order_type' => $target->order_type?->value,
                'note' => trim(($target->note ?? '').' '.($source->note ?? '')) ?: null,
            ]);
            $this->sales->discardHeld($source);

            return $merged;
        });
    }

    private function lockHeld(Sale $sale): Sale
    {
        $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->first();
        if (! $sale || $sale->status !== SaleStatus::Held) {
            throw new BusinessException('Bill ini sudah dibayar atau dihapus di perangkat lain. Muat ulang daftar meja.');
        }

        return $sale;
    }
}
