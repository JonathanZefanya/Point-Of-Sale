<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;

/** @mixin Promotion */
class PromotionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $voucher = $this->relationLoaded('vouchers') ? $this->vouchers->first() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'value' => $this->value,
            'rules' => $this->rules_json ?: (object) [],
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'outlet_ids' => $this->outlet_ids,
            'requires_voucher' => $this->requires_voucher,
            'is_active' => $this->is_active,
            'voucher_code' => $voucher?->code,
            'voucher_quota' => $voucher?->quota,
            'voucher_used' => $voucher?->used_count,
        ];
    }
}
