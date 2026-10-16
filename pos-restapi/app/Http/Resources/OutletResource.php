<?php

namespace App\Http\Resources;

use App\Models\Outlet;
use Illuminate\Http\Request;

/** @mixin Outlet */
class OutletResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'tax_rate' => $this->tax_rate,
            'service_charge_rate' => $this->service_charge_rate,
            'receipt_header' => $this->receipt_header,
            'receipt_footer' => $this->receipt_footer,
        ];
    }
}
