<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Varian produk (ukuran/warna/rasa) dengan SKU, harga, dan stok sendiri. */
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'name', 'sku', 'barcode', 'sell_price', 'cost_price', 'is_active', 'sort_order'];

    protected $attributes = ['cost_price' => 0, 'is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'sell_price' => 'integer',
            'cost_price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
