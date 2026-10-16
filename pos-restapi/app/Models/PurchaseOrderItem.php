<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_order_id', 'product_id', 'variant_id', 'product_name', 'quantity', 'received_quantity', 'cost_price', 'total'];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'cost_price' => 'integer',
            'total' => 'integer',
        ];
    }

    public function remaining(): int
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }
}
