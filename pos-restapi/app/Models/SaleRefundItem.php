<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleRefundItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['refund_id', 'sale_item_id', 'quantity', 'line_total', 'cost_total', 'restock'];

    protected function casts(): array
    {
        return [
            'sale_item_id' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'cost_total' => 'integer',
            'restock' => 'boolean',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(SaleRefund::class, 'refund_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
