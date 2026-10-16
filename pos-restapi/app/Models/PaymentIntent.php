<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** QR pembayaran dinamis (3.5). status: pending → paid | expired | failed. */
class PaymentIntent extends Model
{
    protected $fillable = [
        'provider', 'order_id', 'outlet_id', 'user_id', 'sale_id', 'amount', 'status',
        'qr_url', 'qr_string', 'transaction_id', 'expires_at', 'paid_at', 'last_notification',
    ];

    protected function casts(): array
    {
        return [
            'outlet_id' => 'integer',
            'user_id' => 'integer',
            'sale_id' => 'integer',
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_notification' => 'array',
        ];
    }
}
