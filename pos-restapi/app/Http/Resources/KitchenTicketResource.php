<?php

namespace App\Http\Resources;

use App\Enums\OrderType;
use App\Models\KitchenTicket;
use Illuminate\Http\Request;

/** @mixin KitchenTicket */
class KitchenTicketResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'label' => $this->label,
            'order_type' => $this->order_type,
            'order_type_label' => $this->order_type ? OrderType::tryFrom($this->order_type)?->label() : null,
            'status' => $this->status,
            'status_label' => KitchenTicket::STATUSES[$this->status] ?? $this->status,
            'is_addition' => $this->is_addition,
            'items' => $this->items,
            'created_at' => $this->created_at,
            'ready_at' => $this->ready_at,
        ];
    }
}
