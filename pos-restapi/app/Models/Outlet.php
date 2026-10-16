<?php

namespace App\Models;

use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    /** @use HasFactory<OutletFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'tax_rate',
        'service_charge_rate',
        'receipt_header',
        'receipt_footer',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'float',
            'service_charge_rate' => 'float',
        ];
    }

    /** Owner melihat semua outlet; role lain hanya outlet yang ditugaskan. */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! $actor->role->seesAllOutlets()) {
            $query->whereIn('id', $actor->outletIds());
        }
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
