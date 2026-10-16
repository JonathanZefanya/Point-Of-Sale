<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_opname_id', 'product_id', 'variant_id', 'product_name', 'sku', 'barcode', 'system_quantity', 'counted_quantity', 'difference'];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'difference' => 'integer',
        ];
    }
}
