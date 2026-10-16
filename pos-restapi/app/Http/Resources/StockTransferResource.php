<?php

namespace App\Http\Resources;

use App\Models\StockTransfer;
use Illuminate\Http\Request;

/** @mixin StockTransfer */
class StockTransferResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $outlet = fn ($o) => ['id' => $o->id, 'code' => $o->code, 'name' => $o->name];

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => StockTransfer::STATUS_LABELS[$this->status] ?? $this->status,
            'from_outlet' => $this->whenLoaded('fromOutlet', fn () => $outlet($this->fromOutlet)),
            'to_outlet' => $this->whenLoaded('toOutlet', fn () => $outlet($this->toOutlet)),
            // Aksi yang boleh dilakukan user ini (UI menampilkan tombol sesuai)
            'can_receive' => $this->status === StockTransfer::IN_TRANSIT && $user?->canAccessOutlet($this->to_outlet_id),
            'can_cancel' => $this->status === StockTransfer::IN_TRANSIT && $user?->canAccessOutlet($this->from_outlet_id),
            'note' => $this->note,
            'receive_note' => $this->receive_note,
            'created_by' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'received_by' => $this->whenLoaded('receiver', fn () => $this->receiver ? ['id' => $this->receiver->id, 'name' => $this->receiver->name] : null),
            'received_at' => $this->received_at,
            'items_count' => $this->whenCounted('items'),
            'total_quantity' => $this->whenHas('total_quantity', fn () => (int) $this->total_quantity),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'variant_id' => $i->variant_id,
                'product_name' => $i->product_name,
                'quantity' => $i->quantity,
                'received_quantity' => $i->received_quantity,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
