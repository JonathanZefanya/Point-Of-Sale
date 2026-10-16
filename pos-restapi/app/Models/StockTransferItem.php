<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_transfer_id', 'product_id', 'variant_id', 'product_name', 'quantity', 'received_quantity', 'cost_price'];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'cost_price' => 'integer',
        ];
    }
}
