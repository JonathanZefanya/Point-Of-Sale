<?php

namespace App\Http\Resources;

use App\Models\StockOpname;
use Illuminate\Http\Request;

/** @mixin StockOpname */
class StockOpnameResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => StockOpname::STATUS_LABELS[$this->status] ?? $this->status,
            'outlet' => $this->whenLoaded('outlet', fn () => ['id' => $this->outlet->id, 'code' => $this->outlet->code, 'name' => $this->outlet->name]),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'note' => $this->note,
            'created_by' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'applied_by' => $this->whenLoaded('applier', fn () => $this->applier ? ['id' => $this->applier->id, 'name' => $this->applier->name] : null),
            'applied_at' => $this->applied_at,
            'items_count' => $this->whenCounted('items'),
            'counted_count' => $this->whenHas('counted_count', fn () => (int) $this->counted_count),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'variant_id' => $i->variant_id,
                'product_name' => $i->product_name,
                'sku' => $i->sku,
                'barcode' => $i->barcode,
                'system_quantity' => $i->system_quantity,
                'counted_quantity' => $i->counted_quantity,
                'difference' => $i->difference,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
