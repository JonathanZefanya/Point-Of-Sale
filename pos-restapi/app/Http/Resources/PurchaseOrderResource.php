<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'editable' => $this->status->editable(),
            'receivable' => $this->status->receivable(),
            'outlet' => $this->whenLoaded('outlet', fn () => ['id' => $this->outlet->id, 'code' => $this->outlet->code, 'name' => $this->outlet->name]),
            'supplier' => $this->whenLoaded('supplier', fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name, 'phone' => $this->supplier->phone]),
            'created_by' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'total' => $this->total,
            'note' => $this->note,
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'variant_id' => $i->variant_id,
                'product_name' => $i->product_name,
                'quantity' => $i->quantity,
                'received_quantity' => $i->received_quantity,
                'remaining' => $i->remaining(),
                'cost_price' => $i->cost_price,
                'total' => $i->total,
            ])),
            'ordered_at' => $this->ordered_at,
            'received_at' => $this->received_at,
            'created_at' => $this->created_at,
        ];
    }
}
