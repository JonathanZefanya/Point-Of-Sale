<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Transaksi penjualan. Tidak pernah dihapus — dibatalkan lewat status void. */
class Sale extends Model
{
    protected $fillable = [
        'number', 'outlet_id', 'shift_id', 'user_id', 'customer_id', 'status', 'order_type', 'hold_label',
        'subtotal', 'discount_total', 'tax_total', 'service_charge', 'rounding', 'grand_total',
        'paid_total', 'change_amount', 'points_earned', 'points_redeemed', 'note', 'idempotency_key',
        'voided_by', 'void_reason', 'voided_at', 'completed_at', 'voucher_id', 'manual_discount', 'promotions_json', 'synced_at', 'sync_warnings', 'table_id', 'table_name', 'kitchen_sent_json',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'order_type' => OrderType::class,
            'table_id' => 'integer',
            'outlet_id' => 'integer',
            'shift_id' => 'integer',
            'customer_id' => 'integer',
            'user_id' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'service_charge' => 'integer',
            'rounding' => 'integer',
            'grand_total' => 'integer',
            'paid_total' => 'integer',
            'change_amount' => 'integer',
            'points_earned' => 'integer',
            'points_redeemed' => 'integer',
            'voucher_id' => 'integer',
            'manual_discount' => 'integer',
            'promotions_json' => 'array',
            'synced_at' => 'datetime',
            'sync_warnings' => 'array',
            'kitchen_sent_json' => 'array',
            'voided_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Dibatasi ke outlet yang boleh diakses user (CLAUDE.md §4). */
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id')->withTrashed();
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class)->orderBy('id');
    }
}
