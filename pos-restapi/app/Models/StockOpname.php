<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stock opname (F8). status: draft → applied | cancelled. */
class StockOpname extends Model
{
    public const DRAFT = 'draft';

    public const APPLIED = 'applied';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [self::DRAFT => 'Draf', self::APPLIED => 'Diterapkan', self::CANCELLED => 'Dibatalkan'];

    protected $fillable = ['number', 'outlet_id', 'category_id', 'status', 'note', 'user_id', 'applied_by', 'applied_at'];

    protected function casts(): array
    {
        return ['outlet_id' => 'integer', 'category_id' => 'integer', 'applied_at' => 'datetime'];
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class)->orderBy('product_name');
    }
}
