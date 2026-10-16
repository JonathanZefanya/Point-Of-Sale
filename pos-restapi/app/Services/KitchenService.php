<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\KitchenTicket;
use App\Models\Sale;
use App\Models\Setting;

/**
 * Kitchen Display (3.4). Saat bill disimpan atau dibayar, item yang belum pernah dikirim ke dapur
 * dijadikan tiket. Jumlah terkirim per item dicatat di sales.kitchen_sent_json.
 */
class KitchenService
{
    public function enabled(): bool
    {
        return (bool) Setting::get('kitchen.enabled', false);
    }

    /** Kirim item baru transaksi ke dapur. Dipanggil di dalam transaksi DB (hold/checkout). */
    public function dispatch(Sale $sale): ?KitchenTicket
    {
        if (! $this->enabled()) {
            return null;
        }
        $sent = $sale->kitchen_sent_json ?? [];
        $current = $this->summarize($sale);

        $items = [];
        foreach ($current as $key => $row) {
            $delta = $row['quantity'] - ($sent[$key] ?? 0);
            if ($delta > 0) {
                $items[] = [...$row, 'quantity' => $delta];
            }
            // Item yang dikurangi setelah dikirim tetap dihitung terkirim (dapur mungkin sudah memasaknya)
            $sent[$key] = max($sent[$key] ?? 0, $row['quantity']);
        }
        $sale->forceFill(['kitchen_sent_json' => $sent])->saveQuietly();
        if (! $items) {
            return null;
        }

        return KitchenTicket::query()->create([
            'outlet_id' => $sale->outlet_id,
            'sale_id' => $sale->id,
            'label' => $sale->table_name ?? $sale->hold_label ?? $sale->number ?? 'Pesanan',
            'order_type' => $sale->order_type?->value,
            'status' => 'new',
            'items' => $items,
            'is_addition' => KitchenTicket::query()->where('sale_id', $sale->id)->exists(),
        ]);
    }

    /** Gabung catatan item terkirim dua bill (gabung meja) agar item yang sudah dimasak tidak dikirim ulang. */
    public function mergeSent(Sale $target, Sale $source): void
    {
        $sent = $target->kitchen_sent_json ?? [];
        foreach ($source->kitchen_sent_json ?? [] as $key => $qty) {
            $sent[$key] = ($sent[$key] ?? 0) + $qty;
        }
        $target->forceFill(['kitchen_sent_json' => $sent])->saveQuietly();
    }

    public function setStatus(KitchenTicket $ticket, string $status): KitchenTicket
    {
        if (! array_key_exists($status, KitchenTicket::STATUSES)) {
            throw new BusinessException('Status tiket tidak dikenal.', 422);
        }
        $ticket->update([
            'status' => $status,
            'ready_at' => $status === 'ready' ? now() : $ticket->ready_at,
            'served_at' => $status === 'served' ? now() : null,
        ]);

        return $ticket;
    }

    /** @return array<string, array{name: string, quantity: int, modifiers: list<string>, note: ?string}> */
    private function summarize(Sale $sale): array
    {
        $rows = [];
        foreach ($sale->items()->get() as $item) {
            $modifiers = collect($item->modifiers_json ?? []);
            $key = implode(':', [$item->product_id, $item->variant_id ?? 0, $modifiers->pluck('id')->sort()->implode(','), md5((string) $item->note)]);
            $rows[$key] ??= ['name' => $item->product_name, 'quantity' => 0, 'modifiers' => $modifiers->pluck('name')->all(), 'note' => $item->note];
            $rows[$key]['quantity'] += $item->quantity;
        }

        return $rows;
    }
}
