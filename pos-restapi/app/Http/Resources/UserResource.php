<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'can_authorize' => $this->role->canAuthorize(),
            'is_active' => $this->is_active,
            'commission_percent' => (float) $this->commission_percent,
            'has_pin' => $this->pin !== null,
            'outlets' => OutletResource::collection($this->whenLoaded('outlets')),
            'created_at' => $this->created_at,
        ];
    }
}
