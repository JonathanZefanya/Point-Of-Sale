<?php

namespace App\Services\Tables;

use App\Enums\SaleStatus;
use App\Exceptions\BusinessException;
use App\Models\DiningTable;
use App\Models\Sale;

/** Satu meja = paling banyak satu bill terbuka (3.3). Dipanggil di dalam transaksi DB. */
class TableOccupancy
{
    /** Kunci meja lalu pastikan belum dipakai bill lain (selain $exceptSaleId). */
    public function lockFree(int $tableId, int $outletId, ?int $exceptSaleId = null): DiningTable
    {
        $table = DiningTable::query()->whereKey($tableId)->where('outlet_id', $outletId)->where('is_active', true)->lockForUpdate()->first();
        if (! $table) {
            throw new BusinessException('Meja tidak ditemukan atau tidak aktif di outlet ini. Muat ulang daftar meja.', 422, ['errors' => ['table_id' => ['Pilih meja yang tersedia.']]]);
        }

        $other = Sale::query()->where('table_id', $table->id)->where('status', SaleStatus::Held)
            ->when($exceptSaleId, fn ($q) => $q->whereKeyNot($exceptSaleId))->first();
        if ($other) {
            $label = $other->hold_label ? " ({$other->hold_label})" : '';
            throw new BusinessException("Meja {$table->name} sudah terisi bill lain{$label}. Buka bill meja itu, gabungkan, atau pilih meja lain.", 409, ['table_sale_id' => $other->id]);
        }

        return $table;
    }
}
