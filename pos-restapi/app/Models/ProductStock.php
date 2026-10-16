<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo stok per outlet. JANGAN diubah langsung — selalu lewat App\Services\StockService
 * agar setiap perubahan tercatat di stock_movements (CLAUDE.md §4).
 */
class ProductStock extends Model
{
    protected $fillable = ['outlet_id', 'product_id', 'variant_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'outlet_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
