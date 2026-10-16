<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;

/** @mixin Category */
class CategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
