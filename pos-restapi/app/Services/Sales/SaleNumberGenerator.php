<?php

namespace App\Services\Sales;

use App\Models\Outlet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Nomor transaksi {KODE_OUTLET}-{YYYYMMDD}-{urut 4 digit}, reset per hari per outlet (CLAUDE.md §4).
 *
 * Dipanggil di dalam transaksi DB. Baris outlet dikunci lebih dulu sehingga penomoran per outlet
 * berjalan satu per satu — termasuk saat baris urutan hari itu belum ada.
 * (Versi awal insertOrIgnore + FOR UPDATE deadlock saat beberapa kasir checkout bersamaan.)
 */
class SaleNumberGenerator
{
    public function next(Outlet $outlet, CarbonInterface $at): string
    {
        Outlet::query()->whereKey($outlet->id)->lockForUpdate()->first();

        $date = $at->toDateString();
        $sequence = DB::table('sale_sequences')->where('outlet_id', $outlet->id)->where('sale_date', $date);

        // Wajib locking read: SELECT biasa di REPEATABLE READ membaca snapshot lama
        // (dibuat saat query pertama transaksi) sehingga nomor bisa dobel.
        $last = (clone $sequence)->lockForUpdate()->value('last_number');

        if ($last === null) {
            DB::table('sale_sequences')->insert(['outlet_id' => $outlet->id, 'sale_date' => $date, 'last_number' => 1]);
        } else {
            (clone $sequence)->update(['last_number' => $last + 1]);
        }

        return sprintf('%s-%s-%04d', $outlet->code, $at->format('Ymd'), (int) $last + 1);
    }
}
