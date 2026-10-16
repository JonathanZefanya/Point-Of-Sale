<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pesanan pembelian ke supplier (F9). Stok bertambah hanya saat barang diterima. */
class PurchaseOrder extends Model
{
    protected $fillable = ['number', 'outlet_id', 'supplier_id', 'user_id', 'status', 'total', 'note', 'ordered_at', 'received_at'];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'outlet_id' => 'integer',
            'supplier_id' => 'integer',
            'total' => 'integer',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->role->seesAllOutlets()) {
            $query->whereIn('outlet_id', $user->outletIds());
        }
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }
}
