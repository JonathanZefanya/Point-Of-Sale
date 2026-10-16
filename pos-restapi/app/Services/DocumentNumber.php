<?php

namespace App\Services;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Model;

/**
 * Nomor dokumen {PREFIX}-{KODE_OUTLET}-{YYYYMMDD}-{urut 4 digit}, reset per hari per outlet
 * (PO, opname, transfer). Dipanggil di dalam transaksi DB.
 */
class DocumentNumber
{
    /** @param class-string<Model> $model model dengan kolom `number` */
    public function next(string $prefix, Outlet $outlet, string $model): string
    {
        // Kunci baris outlet agar penomoran per outlet berurutan
        Outlet::query()->whereKey($outlet->id)->lockForUpdate()->first();
        $base = sprintf('%s-%s-%s-', $prefix, $outlet->code, now()->format('Ymd'));
        // Locking read agar membaca nomor terbaru, bukan snapshot (lihat SaleNumberGenerator)
        $last = $model::query()->where('number', 'like', $base.'%')->lockForUpdate()->max('number');

        return $base.sprintf('%04d', $last ? (int) substr($last, -4) + 1 : 1);
    }
}
