<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Bacaan stok (daftar stok & kartu stok). Perubahan stok tetap hanya lewat StockService.
 *
 * "Item stok" = produk tanpa varian ATAU satu varian. Keduanya digabung (UNION ALL) menjadi
 * tabel turunan `items` sehingga filter, urutan, dan paginasi tetap di database.
 */
class StockQueryService
{
    /** @param array<string, mixed> $filters hasil validasi IndexStockRequest */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';
        $column = ltrim($sort, '-') === 'quantity' ? 'quantity' : 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $page = $this->items((int) $filters['outlet_id'])
            ->when($filters['search'] ?? null, fn (QueryBuilder $q, string $s) => $q->where(
                fn (QueryBuilder $q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('variant_name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%")
                    ->orWhere('barcode', $s)
            ))
            ->when($filters['category_id'] ?? null, fn (QueryBuilder $q, int $id) => $q->where('category_id', $id))
            ->when(($filters['status'] ?? null) === 'low', fn (QueryBuilder $q) => $q->whereColumn('quantity', '<=', 'min_stock'))
            ->when(($filters['status'] ?? null) === 'out', fn (QueryBuilder $q) => $q->where('quantity', '<=', 0))
            ->orderBy($column, $direction)
            ->orderBy('name')
            ->orderBy('variant_name')
            ->paginate($filters['per_page'] ?? 20);

        // Nama kategori untuk baris di halaman ini saja
        $categories = Category::query()->whereKey(collect($page->items())->pluck('category_id')->filter()->unique())->pluck('name', 'id');
        foreach ($page->items() as $row) {
            $row->category_name = $categories[$row->category_id] ?? null;
        }

        return $page;
    }

    /**
     * Jumlah item menipis & habis di outlet (untuk badge & dashboard).
     *
     * @return array{low: int, out: int}
     */
    public function summary(int $outletId): array
    {
        $row = $this->items($outletId)
            ->selectRaw('SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END) AS low_count')
            ->selectRaw('SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) AS out_count')
            ->first();

        return ['low' => (int) ($row->low_count ?? 0), 'out' => (int) ($row->out_count ?? 0)];
    }

    /** @param array<string, mixed> $filters hasil validasi StockMovementsRequest */
    public function movements(Product $product, array $filters): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with('user:id,name')
            ->where('outlet_id', $filters['outlet_id'])
            ->where('product_id', $product->id)
            ->when($filters['variant_id'] ?? null, fn (Builder $q, int $id) => $q->where('variant_id', $id), fn (Builder $q) => $q->whereNull('variant_id'))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '>=', "{$d} 00:00:00"))
            ->when($filters['to'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '<=', "{$d} 23:59:59"))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);
    }

    /** Tabel turunan item stok (produk tanpa varian + varian) dengan saldo di outlet. */
    private function items(int $outletId): QueryBuilder
    {
        $stockJoin = fn (?string $variantColumn) => function (JoinClause $join) use ($outletId, $variantColumn) {
            $join->on('ps.product_id', '=', 'p.id')->where('ps.outlet_id', '=', $outletId);
            $variantColumn ? $join->on('ps.variant_id', '=', $variantColumn) : $join->whereNull('ps.variant_id');
        };
        $tracked = fn (QueryBuilder $q) => $q->whereNull('p.deleted_at')
            ->where('p.track_stock', true)
            ->where('p.type', '!=', ProductType::Service->value);

        $plain = DB::table('products as p')
            ->leftJoin('product_stocks as ps', $stockJoin(null))
            ->tap($tracked)
            ->whereNotExists(fn ($q) => $q->from('product_variants as v')->whereColumn('v.product_id', 'p.id')->whereNull('v.deleted_at'))
            ->select([
                'p.id as product_id', DB::raw('NULL as variant_id'), 'p.name as name', DB::raw('NULL as variant_name'),
                'p.sku', 'p.barcode', 'p.unit', 'p.category_id', 'p.min_stock', 'p.cost_price', 'p.is_active',
                DB::raw('COALESCE(ps.quantity, 0) as quantity'),
            ]);

        $variants = DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('product_stocks as ps', $stockJoin('v.id'))
            ->tap($tracked)
            ->whereNull('v.deleted_at')
            ->select([
                'p.id as product_id', 'v.id as variant_id', 'p.name as name', 'v.name as variant_name',
                'v.sku', 'v.barcode', 'p.unit', 'p.category_id', 'p.min_stock', 'v.cost_price',
                DB::raw('CASE WHEN p.is_active = 1 AND v.is_active = 1 THEN 1 ELSE 0 END as is_active'),
                DB::raw('COALESCE(ps.quantity, 0) as quantity'),
            ]);

        return DB::query()->fromSub($plain->unionAll($variants), 'items');
    }
}
