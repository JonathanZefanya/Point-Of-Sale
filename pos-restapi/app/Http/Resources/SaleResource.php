<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;

/** @mixin Sale */
class SaleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $showCost = $request->user()?->role->canManageCatalog();
        // sale_item_id → jumlah yang sudah dikembalikan (dari refund yang dimuat)
        $refunded = $this->relationLoaded('refunds')
            ? $this->refunds->flatMap->items->groupBy('sale_item_id')->map->sum('quantity')
            : collect();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'outlet' => $this->whenLoaded('outlet', fn () => ['id' => $this->outlet->id, 'code' => $this->outlet->code, 'name' => $this->outlet->name]),
            'cashier' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'shift_id' => $this->shift_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone, 'points' => $this->customer->points] : null),
            'hold_label' => $this->hold_label,
            'order_type' => $this->order_type?->value,
            'order_type_label' => $this->order_type?->label(),
            'table' => $this->table_id ? ['id' => $this->table_id, 'name' => $this->table_name] : null,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'manual_discount' => (int) $this->manual_discount,
            'promotions' => $this->promotions_json ?? [],
            'service_charge' => $this->service_charge,
            'tax_total' => $this->tax_total,
            'rounding' => $this->rounding,
            'grand_total' => $this->grand_total,
            'paid_total' => $this->paid_total,
            'change_amount' => $this->change_amount,
            'points_earned' => $this->points_earned,
            'points_redeemed' => $this->points_redeemed,
            'note' => $this->note,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'modifiers' => $item->modifiers_json ?? [],
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'total' => $item->total,
                'note' => $item->note,
                'refunded_quantity' => (int) ($refunded[$item->id] ?? 0),
                ...($showCost ? ['cost_price' => $item->cost_price] : []),
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'method' => $p->method->value,
                'label' => $p->method->label(),
                'amount' => $p->amount,
                'reference' => $p->reference,
            ])),
            'completed_at' => $this->completed_at,
            'synced_at' => $this->synced_at, // terisi bila transaksi dibuat offline
            'sync_warnings' => $this->sync_warnings ?? [],
            'created_at' => $this->created_at,
            'refunds' => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn ($r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'method' => $r->method->value,
                'method_label' => $r->method->label(),
                'reason' => $r->reason,
                'approved_by' => $r->approver ? ['id' => $r->approver->id, 'name' => $r->approver->name] : null,
                'created_at' => $r->created_at,
                'items' => $r->items->map(fn ($i) => ['sale_item_id' => $i->sale_item_id, 'quantity' => $i->quantity, 'restock' => $i->restock]),
            ])),
            'refunded_total' => $this->whenLoaded('refunds', fn () => (int) $this->refunds->sum('amount')),
            $this->mergeWhen($this->voided_at !== null, fn () => [
                'voided_at' => $this->voided_at,
                'void_reason' => $this->void_reason,
                'voided_by' => $this->voider ? ['id' => $this->voider->id, 'name' => $this->voider->name] : null,
            ]),
        ];
    }
}
