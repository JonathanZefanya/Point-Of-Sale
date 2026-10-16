<?php

namespace App\Services\Sales;

use App\Models\Sale;
use Illuminate\Support\Facades\URL;

/**
 * Kirim struk via WhatsApp (3.6) dengan link wa.me — tanpa provider berbayar.
 * Link struk online ditandatangani (berlaku 30 hari) sehingga struk lain tidak bisa ditebak.
 */
class ReceiptShareService
{
    public const VALID_DAYS = 30;

    /** @return array{url: string, text: string, phone: ?string, whatsapp_url: string} */
    public function share(Sale $sale): array
    {
        $sale->loadMissing(['outlet', 'customer']);
        $api = URL::temporarySignedRoute('receipts.public', now()->addDays(self::VALID_DAYS), ['sale' => $sale->id], absolute: false);
        // Halaman web /struk/{id} meneruskan expires & signature ke API publik
        $query = parse_url($api, PHP_URL_QUERY);
        $url = rtrim((string) config('app.frontend_url'), '/')."/struk/{$sale->id}?{$query}";

        $business = $sale->outlet->name;
        $text = implode("\n", array_filter([
            "Terima kasih telah berbelanja di {$business}.",
            "No. transaksi: {$sale->number}",
            'Total: Rp'.number_format($sale->grand_total, 0, ',', '.'),
            $sale->points_earned ? "Poin didapat: {$sale->points_earned}" : null,
            "Lihat struk: {$url}",
        ]));
        $phone = $this->normalizePhone($sale->customer?->phone);

        return [
            'url' => $url,
            'text' => $text,
            'phone' => $phone,
            'whatsapp_url' => 'https://wa.me/'.($phone ?? '').'?text='.rawurlencode($text),
        ];
    }

    /** 0812-3456-789 / +62812... → 62812... (format wa.me). */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return strlen($digits) >= 9 ? $digits : null;
    }
}
