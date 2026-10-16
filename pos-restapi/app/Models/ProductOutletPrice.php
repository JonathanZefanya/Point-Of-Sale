<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Harga khusus & ketersediaan produk di satu outlet (2.8). */
class ProductOutletPrice extends Model
{
    protected $fillable = ['product_id', 'outlet_id', 'sell_price', 'is_available'];

    protected function casts(): array
    {
        return ['product_id' => 'integer', 'outlet_id' => 'integer', 'sell_price' => 'integer', 'is_available' => 'boolean'];
    }
}
