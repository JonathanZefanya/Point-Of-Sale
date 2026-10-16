<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;

/** @mixin Supplier */
class SupplierResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'note' => $this->note,
            'open_orders' => $this->whenHas('open_orders_count', fn () => (int) $this->open_orders_count),
        ];
    }
}
