<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Transfer stok antar outlet (F10). status: in_transit → received | cancelled. */
class StockTransfer extends Model
{
    public const IN_TRANSIT = 'in_transit';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [self::IN_TRANSIT => 'Dalam perjalanan', self::RECEIVED => 'Diterima', self::CANCELLED => 'Dibatalkan'];

    protected $fillable = ['number', 'from_outlet_id', 'to_outlet_id', 'status', 'note', 'receive_note', 'user_id', 'received_by', 'received_at'];

    protected function casts(): array
    {
        return ['from_outlet_id' => 'integer', 'to_outlet_id' => 'integer', 'received_at' => 'datetime'];
    }

    /** Terlihat bila user punya akses ke outlet asal atau tujuan. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->role->seesAllOutlets()) {
            $ids = $user->outletIds();
            $query->where(fn ($q) => $q->whereIn('from_outlet_id', $ids)->orWhereIn('to_outlet_id', $ids));
        }
    }

    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id')->withTrashed();
    }

    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class)->orderBy('id');
    }
}
