<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerService
{
    /** Status transaksi yang dihitung sebagai belanja pelanggan. */
    private const COUNTED = [SaleStatus::Completed, SaleStatus::PartialRefund, SaleStatus::Refunded];

    /** @param array{search?: ?string, group?: ?string, per_page?: ?int} $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $phone = Customer::normalizePhone($filters['search'] ?? null);

        return $this->withStats(Customer::query())
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$s}%")
                ->when($phone, fn ($q) => $q->orWhere('phone', 'like', "%{$phone}%"))))
            ->when($filters['group'] ?? null, fn (Builder $q, string $g) => $q->where('group', $g))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    /** Pelanggan + jumlah kunjungan & total belanja. */
    public function withStatsFind(int $id): Customer
    {
        return $this->withStats(Customer::query())->findOrFail($id);
    }

    private function withStats(Builder $query): Builder
    {
        $counted = fn ($q) => $q->whereIn('status', self::COUNTED);

        return $query->withCount(['sales' => $counted])->withSum(['sales' => $counted], 'grand_total');
    }
}
