<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Outlet;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;

/** Kelola outlet (2.8). Outlet dihapus secara soft delete: transaksi & stok lama tetap merujuk ke outlet ini. */
class OutletService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function save(Outlet $outlet, array $data): Outlet
    {
        return DB::transaction(function () use ($outlet, $data) {
            $before = $outlet->exists ? $outlet->only(['code', 'name', 'tax_rate', 'service_charge_rate']) : null;
            $outlet->fill($data)->save();
            $this->audit->log($before ? 'outlet.updated' : 'outlet.created', $outlet, $before, $outlet->only(['code', 'name', 'tax_rate', 'service_charge_rate']));

            return $outlet;
        });
    }

    public function delete(Outlet $outlet): void
    {
        DB::transaction(function () use ($outlet) {
            Outlet::query()->whereKey($outlet->id)->lockForUpdate()->first();
            if (Outlet::query()->count() <= 1) {
                throw new BusinessException('Outlet terakhir tidak bisa dihapus. Tambahkan outlet lain terlebih dahulu.');
            }
            if (Shift::query()->where('outlet_id', $outlet->id)->whereNull('closed_at')->exists()) {
                throw new BusinessException("Masih ada shift terbuka di {$outlet->name}. Tutup semua shift sebelum menghapus outlet.");
            }
            $outlet->delete();
            $this->audit->log('outlet.deleted', $outlet, ['code' => $outlet->code, 'name' => $outlet->name]);
        });
    }
}
