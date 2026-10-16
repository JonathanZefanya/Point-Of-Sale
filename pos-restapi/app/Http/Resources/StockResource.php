<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Satu baris daftar stok: produk tanpa varian, atau satu varian (dari StockQueryService::items()).
 *
 * @property object $resource baris tabel turunan items
 */
class StockResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $quantity = (int) $row->quantity;
        $min = (int) $row->min_stock;
        $cost = (int) $row->cost_price;

        return [
            'product_id' => (int) $row->product_id,
            'variant_id' => $row->variant_id !== null ? (int) $row->variant_id : null,
            'name' => $row->variant_name ? "{$row->name} - {$row->variant_name}" : $row->name,
            'product_name' => $row->name,
            'variant_name' => $row->variant_name,
            'sku' => $row->sku,
            'unit' => $row->unit,
            'category' => isset($row->category_name) && $row->category_name ? ['id' => (int) $row->category_id, 'name' => $row->category_name] : null,
            'is_active' => (bool) $row->is_active,
            'quantity' => $quantity,
            'min_stock' => $min,
            // out = habis, low = menipis (≤ stok minimum), ok = aman
            'status' => match (true) {
                $quantity <= 0 => 'out',
                $quantity <= $min => 'low',
                default => 'ok',
            },
            $this->mergeWhen($request->user()?->role->canManageCatalog(), fn () => [
                'cost_price' => $cost,
                'stock_value' => max(0, $quantity) * $cost, // nilai persediaan (rupiah)
            ]),
        ];
    }
}
