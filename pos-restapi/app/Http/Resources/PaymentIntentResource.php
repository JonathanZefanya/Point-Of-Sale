<?php

namespace App\Http\Resources;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;

/** @mixin PaymentIntent */
class PaymentIntentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'qr_url' => $this->qr_url,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'used' => $this->sale_id !== null,
        ];
    }
}
