<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Riwayat transaksi & daftar transaksi tersimpan. */
class SaleQueryService
{
    /** @param array<string, mixed> $filters hasil validasi IndexSaleRequest */
    public function history(User $user, array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? '-completed_at';

        return Sale::query()
            ->visibleTo($user)
            ->with(['user', 'outlet', 'payments', 'customer'])
            ->where('status', '!=', SaleStatus::Held)
            ->when($filters['outlet_id'] ?? null, fn (Builder $q, int $id) => $q->where('outlet_id', $id))
            ->when($filters['shift_id'] ?? null, fn (Builder $q, int $id) => $q->where('shift_id', $id))
            ->when($filters['user_id'] ?? null, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where('number', 'like', "%{$s}%"))
            ->when($filters['from'] ?? null, fn (Builder $q, string $d) => $q->where('completed_at', '>=', "{$d} 00:00:00"))
            ->when($filters['to'] ?? null, fn (Builder $q, string $d) => $q->where('completed_at', '<=', "{$d} 23:59:59"))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);
    }

    /** @return Collection<int, Sale> transaksi tersimpan di outlet (dipakai bersama semua kasir outlet) */
    public function held(int $outletId): Collection
    {
        return Sale::query()
            ->with(['items', 'user', 'customer'])
            ->where('outlet_id', $outletId)
            ->where('status', SaleStatus::Held)
            ->orderByDesc('updated_at')
            ->get();
    }
}
