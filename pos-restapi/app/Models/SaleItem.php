<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Baris transaksi: nama, SKU, harga, modal, dan pajak di-snapshot saat transaksi. */
class SaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'product_id', 'variant_id', 'category_id', 'category_name', 'product_name', 'sku', 'quantity',
        'unit_price', 'cost_price', 'discount', 'tax', 'total', 'modifiers_json', 'note',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'cost_price' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'modifiers_json' => 'array',
        ];
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class);
    }

    /** Jumlah yang sudah dikembalikan (butuh refund_items_sum_quantity dari withSum, atau dihitung). */
    public function refundedQuantity(): int
    {
        return (int) ($this->refund_items_sum_quantity ?? $this->refundItems()->sum('quantity'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
