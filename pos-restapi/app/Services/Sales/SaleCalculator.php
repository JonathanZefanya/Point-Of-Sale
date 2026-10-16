<?php

namespace App\Services\Sales;

use App\Models\Product;

/**
 * Hitung ulang total transaksi di server (sumber kebenaran, CLAUDE.md §6).
 * Semua dalam integer rupiah; pembulatan half-up.
 *
 * Urutan: subtotal → service charge → pajak (dasar = subtotal + service) → pembulatan.
 * Pajak inklusif: harga sudah termasuk pajak, jadi pajak hanya dipisahkan untuk laporan.
 */
class SaleCalculator
{
    /**
     * @param  list<array{product: Product, quantity: int, note?: ?string}>  $lines
     */
    public function calculate(array $lines, PricingSettings $settings): CalculatedSale
    {
        $rows = [];
        $subtotal = 0;
        $discountTotal = 0;
        foreach ($lines as $line) {
            // Harga baris: varian + modifier (diisi SaleService), selain itu harga produk
            $unit = $line['unit_price'] ?? $line['product']->sell_price;
            $gross = $unit * $line['quantity'];
            $discount = min($gross, $line['discount'] ?? 0); // dari PromotionEngine
            $subtotal += $gross;
            $discountTotal += $discount;
            $rows[] = [
                ...$line,
                'unit_price' => $unit,
                'cost_price' => $line['cost_price'] ?? $line['product']->cost_price,
                'discount' => $discount,
                'total' => $gross - $discount, // nilai bersih baris (dasar pajak & laporan per produk)
                'note' => $line['note'] ?? null,
            ];
        }
        $service = $this->percent($subtotal - $discountTotal, $settings->serviceBasisPoints);
        $taxBase = $subtotal - $discountTotal + $service;

        $tax = $settings->taxInclusive
            ? $taxBase - $this->divideRounded($taxBase * 10000, 10000 + $settings->taxBasisPoints)
            : $this->percent($taxBase, $settings->taxBasisPoints);

        $beforeRounding = $taxBase + ($settings->taxInclusive ? 0 : $tax);
        $grandTotal = $settings->roundingEnabled
            ? $this->roundTo($beforeRounding, $settings->roundingTo, $settings->roundingMode)
            : $beforeRounding;

        return new CalculatedSale(
            lines: $this->allocateTax($rows, $tax, $subtotal - $discountTotal),
            subtotal: $subtotal,
            discountTotal: $discountTotal,
            serviceCharge: $service,
            taxTotal: $tax,
            rounding: $grandTotal - $beforeRounding,
            grandTotal: $grandTotal,
        );
    }

    /** Bagi pajak ke baris secara proporsional; sisa pembulatan ke baris terakhir agar jumlahnya pas. */
    private function allocateTax(array $rows, int $tax, int $subtotal): array
    {
        $allocated = 0;
        foreach ($rows as $i => $row) {
            $isLast = $i === array_key_last($rows);
            $share = $isLast || $subtotal === 0 ? $tax - $allocated : $this->divideRounded($tax * $row['total'], $subtotal);
            $rows[$i]['tax'] = $subtotal === 0 ? 0 : $share;
            $allocated += $rows[$i]['tax'];
        }

        return $rows;
    }

    private function percent(int $amount, int $basisPoints): int
    {
        return $this->divideRounded($amount * $basisPoints, 10000);
    }

    /** Pembagian integer dengan pembulatan half-up (untuk bilangan non-negatif). */
    private function divideRounded(int $numerator, int $denominator): int
    {
        return intdiv($numerator * 2 + $denominator, $denominator * 2);
    }

    public function roundTo(int $value, int $to, string $mode): int
    {
        return match ($mode) {
            'up' => intdiv($value + $to - 1, $to) * $to,
            'down' => intdiv($value, $to) * $to,
            default => intdiv($value * 2 + $to, $to * 2) * $to,
        };
    }
}
