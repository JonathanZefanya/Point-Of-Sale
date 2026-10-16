<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/** @mixin Product */
class ProductResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $canSeeCost = (bool) $request->user()?->role->canManageCatalog();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'unit' => $this->unit,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name]
                : null),
            'category_id' => $this->category_id,
            // Dengan konteks outlet (kasir): harga khusus outlet bila ada
            'sell_price' => $this->effectivePrice(),
            'is_available' => $this->when($this->availableAtOutlet !== null, fn () => $this->availableAtOutlet),
            'outlet_prices' => $this->when($canSeeCost && $this->relationLoaded('outletPrices'), fn () => $this->outletPrices->map(fn ($p) => [
                'outlet_id' => $p->outlet_id,
                'sell_price' => $p->sell_price,
                'is_available' => $p->is_available,
            ])->values()),
            // Harga modal & margin hanya untuk yang boleh mengelola katalog, bukan kasir
            $this->mergeWhen($canSeeCost, fn () => [
                'cost_price' => $this->cost_price,
            ]),
            'track_stock' => $this->track_stock,
            'min_stock' => $this->min_stock,
            'image_url' => $this->imageUrl(),
            'is_active' => $this->is_active,
            'has_variants' => $this->whenLoaded('variants', fn () => $this->hasVariants()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'sell_price' => $v->sell_price,
                ...($canSeeCost ? ['cost_price' => $v->cost_price] : []),
                'is_active' => $v->is_active,
            ])->values()),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => ModifierGroupResource::collection($this->modifierGroups)->resolve()),
            'matched_variant_id' => $this->when($this->matched_variant_id !== null, fn () => $this->matched_variant_id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
