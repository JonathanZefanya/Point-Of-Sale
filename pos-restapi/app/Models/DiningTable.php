<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Meja restoran (3.3). Terisi = ada transaksi tersimpan (bill terbuka) di meja ini. */
class DiningTable extends Model
{
    use SoftDeletes;

    protected $fillable = ['outlet_id', 'name', 'area', 'capacity', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['outlet_id' => 'integer', 'capacity' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** Bill terbuka di meja ini (paling banyak satu). */
    public function openSale(): HasOne
    {
        return $this->hasOne(Sale::class, 'table_id')->where('status', SaleStatus::Held);
    }
}
