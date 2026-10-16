<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;

/** @mixin Customer */
class CustomerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'birth_date' => $this->birth_date?->toDateString(),
            'group' => $this->group->value,
            'group_label' => $this->group->label(),
            'points' => $this->points,
            'note' => $this->note,
            // Ringkasan belanja (bila dihitung di query)
            'visits' => $this->whenCounted('sales'),
            'total_spent' => $this->when(isset($this->sales_sum_grand_total), fn () => (int) $this->sales_sum_grand_total),
            'created_at' => $this->created_at,
        ];
    }
}
