<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * SKU otomatis: {3 huruf awal kategori}-{urut 4 digit}, mis. "MIN-0001".
 * Tanpa kategori → "PRD-0001". Urutan dihitung termasuk produk terhapus agar tidak bentrok.
 */
class SkuGenerator
{
    public const FALLBACK_PREFIX = 'PRD';

    public function next(?Category $category): string
    {
        $prefix = $this->prefixFor($category);

        // Dipanggil di dalam DB::transaction() oleh ProductService; lock mencegah dua SKU sama
        $last = Product::withTrashed()
            ->where('sku', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->pluck('sku')
            ->map(fn (string $sku) => (int) Str::after($sku, "{$prefix}-"))
            ->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $last + 1);
    }

    public function prefixFor(?Category $category): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii($category?->name ?? '')));

        return strlen($letters) >= 3 ? substr($letters, 0, 3) : self::FALLBACK_PREFIX;
    }
}
