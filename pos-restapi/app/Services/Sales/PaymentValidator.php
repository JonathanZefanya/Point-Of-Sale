<?php

namespace App\Services\Sales;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;

/**
 * Aturan pembayaran (F3.5–3.6):
 * - total dibayar ≥ total transaksi (Selesai hanya bila sisa = 0),
 * - non-tunai tidak boleh melebihi tagihan → kembalian hanya dari tunai.
 * Mengembalikan nominal kembalian.
 */
class PaymentValidator
{
    /** @param list<array{method: string, amount: int}> $payments */
    public function changeFor(array $payments, int $grandTotal): int
    {
        $paid = 0;
        $nonCash = 0;
        foreach ($payments as $payment) {
            $paid += $payment['amount'];
            if (! PaymentMethod::from($payment['method'])->isCash()) {
                $nonCash += $payment['amount'];
            }
        }

        if ($nonCash > $grandTotal) {
            $this->fail('Pembayaran non-tunai melebihi total. Kurangi nominal QRIS/kartu/transfer; kembalian hanya dari uang tunai.', $grandTotal, $paid);
        }

        if ($paid < $grandTotal) {
            $short = number_format($grandTotal - $paid, 0, ',', '.');
            $total = number_format($grandTotal, 0, ',', '.');
            $this->fail("Pembayaran kurang Rp{$short}. Total transaksi Rp{$total}.", $grandTotal, $paid);
        }

        return $paid - $grandTotal;
    }

    private function fail(string $message, int $grandTotal, int $paid): never
    {
        // grand_total dikirim agar kasir melihat total versi server bila berbeda dari layar
        throw new BusinessException($message, 422, [
            'errors' => ['payments' => [$message]],
            'grand_total' => $grandTotal,
            'paid_total' => $paid,
        ]);
    }
}
