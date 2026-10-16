<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\SettingService;

/**
 * Data struk siap cetak (BE-1.10). Semua teks sudah final (label Bahasa Indonesia, nama metode bayar),
 * frontend hanya menyusun baris ke kertas 58/80mm.
 */
class ReceiptBuilder
{
    public function __construct(private readonly SettingService $settings) {}

    /** @return array<string, mixed> */
    public function build(Sale $sale): array
    {
        $sale->loadMissing(['items', 'payments', 'outlet', 'user', 'customer']);
        $settings = $this->settings->all();
        $business = $settings['business'] ?? [];
        $receipt = $settings['receipt'] ?? [];

        return [
            'paper_width' => (int) ($receipt['paper_width'] ?? 58),
            'header' => $this->header($sale, $business),
            'number' => $sale->number,
            'datetime' => $sale->completed_at,
            'cashier' => ($receipt['show_cashier'] ?? true) ? $sale->user->name : null,
            'outlet_code' => $sale->outlet->code,
            'customer' => $sale->customer?->name,
            'order_type' => $sale->order_type?->label(),
            'table' => $sale->table_name,
            'items' => $sale->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'total' => $item->total,
                'note' => $item->note,
                'modifiers' => array_map(fn ($m) => $m['price'] ? "{$m['name']} (+".number_format($m['price'], 0, ',', '.').')' : $m['name'], $item->modifiers_json ?? []),
            ])->all(),
            'totals' => array_values(array_filter([
                ['label' => 'Subtotal', 'amount' => $sale->subtotal],
                // Rincian diskon: per promo (snapshot) + diskon manual; jumlahnya = discount_total
                ...array_map(fn ($p) => ['label' => $p['name'], 'amount' => -$p['amount']], $sale->promotions_json ?? []),
                $sale->manual_discount ? ['label' => 'Diskon manual', 'amount' => -$sale->manual_discount] : null,
                $sale->service_charge ? ['label' => 'Service charge', 'amount' => $sale->service_charge] : null,
                $sale->tax_total ? ['label' => ($settings['tax']['inclusive'] ?? false) ? 'Pajak (termasuk)' : 'Pajak', 'amount' => $sale->tax_total] : null,
                $sale->rounding ? ['label' => 'Pembulatan', 'amount' => $sale->rounding] : null,
            ])),
            'grand_total' => $sale->grand_total,
            'payments' => $sale->payments->map(fn ($p) => ['label' => $p->method->label(), 'amount' => $p->amount])->all(),
            'change_amount' => $sale->change_amount,
            'footer' => array_values(array_filter(preg_split('/\R/', (string) $sale->outlet->receipt_footer) ?: [], fn ($l) => filled($l))),
            'is_void' => $sale->status === SaleStatus::Void,
            'auto_print' => (bool) ($receipt['auto_print'] ?? false),
        ];
    }

    /**
     * Header struk: header kustom outlet bila diisi (menggantikan nama/alamat otomatis),
     * selain itu nama outlet + alamat + telepon. NPWP selalu ditambahkan bila ada.
     *
     * @return list<string>
     */
    private function header(Sale $sale, array $business): array
    {
        $outlet = $sale->outlet;
        $custom = array_filter(preg_split('/\R/', (string) $outlet->receipt_header) ?: [], fn ($l) => filled($l));

        $lines = $custom ?: [
            $business['name'] ?? $outlet->name,
            $outlet->address,
            $outlet->phone ? "Telp {$outlet->phone}" : null,
        ];
        $lines[] = ! empty($business['npwp']) ? "NPWP {$business['npwp']}" : null;

        return array_values(array_filter($lines, fn ($line) => filled($line)));
    }
}
