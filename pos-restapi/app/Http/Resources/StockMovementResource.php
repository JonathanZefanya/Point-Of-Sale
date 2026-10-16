<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;

/**
 * Satu baris kartu stok.
 *
 * @mixin StockMovement
 */
class StockMovementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity_change' => $this->quantity_change,
            'quantity_after' => $this->quantity_after,
            'note' => $this->note,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'reference' => $this->reference_type ? ['type' => $this->reference_type, 'id' => $this->reference_id] : null,
            $this->mergeWhen($request->user()?->role->canManageCatalog(), fn () => [
                'cost_price' => $this->cost_price,
            ]),
        ];
    }
}
