<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Sinkronisasi varian dari form produk (2.1): baris dengan id diubah, tanpa id ditambah,
 * yang tidak dikirim di-soft-delete (riwayat penjualan & kartu stok tetap utuh).
 * Dipanggil di dalam transaksi ProductService.
 */
class ProductVariantService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
    ) {}

    /** @param list<array<string, mixed>> $rows */
    public function sync(Product $product, array $rows, ?int $outletId): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $position => $row) {
            $data = [
                'name' => trim($row['name']),
                'barcode' => ($row['barcode'] ?? null) ?: null,
                'sell_price' => (int) $row['sell_price'],
                'cost_price' => (int) ($row['cost_price'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => $position,
            ];

            if (! empty($row['id']) && $variant = $existing->get((int) $row['id'])) {
                $before = ['sell_price' => $variant->sell_price, 'cost_price' => $variant->cost_price];
                $variant->fill([...$data, ...(! empty($row['sku']) ? ['sku' => $row['sku']] : [])])->save();
                $after = ['sell_price' => $variant->sell_price, 'cost_price' => $variant->cost_price];
                if ($before !== $after) {
                    $this->audit->log('product.price_changed', $product, ['variant' => $variant->name, ...$before], ['variant' => $variant->name, ...$after]);
                }
            } else {
                $variant = $product->variants()->create([...$data, 'sku' => ($row['sku'] ?? null) ?: $this->nextSku($product)]);
                $initial = (int) ($row['initial_stock'] ?? 0);
                if ($initial > 0 && $outletId) {
                    $this->stock->change($outletId, $product, $initial, StockMovementType::Initial, $product, 'Stok awal', $variant->id);
                }
            }

            $keep[] = $variant->id;
        }

        $product->variants()->whereKeyNot($keep)->get()->each->delete();
    }

    /** SKU varian otomatis: {SKU produk}-{n}, mis. MIN-0001-2. */
    private function nextSku(Product $product): string
    {
        $n = ProductVariant::withTrashed()->where('product_id', $product->id)->count() + 1;
        while (ProductVariant::withTrashed()->where('sku', "{$product->sku}-{$n}")->exists()) {
            $n++;
        }

        return "{$product->sku}-{$n}";
    }
}
