<?php

namespace App\Http\Resources;

use App\Models\ModifierGroup;
use Illuminate\Http\Request;

/** @mixin ModifierGroup */
class ModifierGroupResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'required' => $this->min_select > 0,
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'price' => $m->price,
            ])->values()),
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
