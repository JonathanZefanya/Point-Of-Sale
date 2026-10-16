<?php

namespace App\Http\Resources;

use App\Models\DiningTable;
use Illuminate\Http\Request;

/** @mixin DiningTable */
class DiningTableResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $sale = $this->relationLoaded('openSale') ? $this->openSale : null;

        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'name' => $this->name,
            'area' => $this->area,
            'capacity' => $this->capacity,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'occupied' => (bool) $sale,
            // Bill terbuka di meja ini (ringkas, untuk denah meja di kasir)
            'open_sale' => $sale ? [
                'id' => $sale->id,
                'hold_label' => $sale->hold_label,
                'grand_total' => $sale->grand_total,
                'items_count' => (int) ($sale->items_count ?? 0),
                'opened_at' => $sale->created_at,
            ] : null,
        ];
    }
}
