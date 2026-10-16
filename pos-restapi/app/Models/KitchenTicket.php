<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tiket dapur (3.4). status: new → preparing → ready → served. */
class KitchenTicket extends Model
{
    public const STATUSES = ['new' => 'Baru', 'preparing' => 'Diproses', 'ready' => 'Siap', 'served' => 'Disajikan'];

    protected $fillable = ['outlet_id', 'sale_id', 'label', 'order_type', 'status', 'items', 'is_addition', 'ready_at', 'served_at'];

    protected function casts(): array
    {
        return [
            'outlet_id' => 'integer',
            'sale_id' => 'integer',
            'items' => 'array',
            'is_addition' => 'boolean',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
