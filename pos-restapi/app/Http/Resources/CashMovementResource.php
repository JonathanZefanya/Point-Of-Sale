<?php

namespace App\Http\Resources;

use App\Models\CashMovement;
use Illuminate\Http\Request;

/** @mixin CashMovement */
class CashMovementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
