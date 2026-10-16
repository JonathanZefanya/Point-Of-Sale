<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Sales\PricingSettings;
use App\Services\Sales\SaleCalculator;
use PHPUnit\Framework\TestCase;

class SaleCalculatorTest extends TestCase
{
    private function product(int $price, int $cost = 0): Product
    {
        return new Product(['name' => 'X', 'sku' => 'X', 'sell_price' => $price, 'cost_price' => $cost]);
    }

    private function settings(int $tax = 0, bool $inclusive = false, int $service = 0, bool $rounding = false, string $mode = 'nearest'): PricingSettings
    {
        return new PricingSettings($tax, $inclusive, $service, $rounding, 100, $mode);
    }

    public function test_exclusive_tax_example_from_claude_md(): void
    {
        // Kopi Susu 2 × 15.000 + Roti Bakar 1 × 18.000, pajak 10% → 48.000 + 4.800 = 52.800
        $calc = (new SaleCalculator)->calculate([
            ['product' => $this->product(15000), 'quantity' => 2],
            ['product' => $this->product(18000), 'quantity' => 1],
        ], $this->settings(tax: 1000));

        $this->assertSame(48000, $calc->subtotal);
        $this->assertSame(4800, $calc->taxTotal);
        $this->assertSame(52800, $calc->grandTotal);
        $this->assertSame([3000, 1800], array_column($calc->lines, 'tax'));
    }

    public function test_service_charge_is_taxed(): void
    {
        // 100.000 + service 5% = 105.000; PB1 10% dari 105.000 = 10.500 → 115.500
        $calc = (new SaleCalculator)->calculate([['product' => $this->product(100000), 'quantity' => 1]], $this->settings(tax: 1000, service: 500));

        $this->assertSame(5000, $calc->serviceCharge);
        $this->assertSame(10500, $calc->taxTotal);
        $this->assertSame(115500, $calc->grandTotal);
    }

    public function test_inclusive_tax_does_not_change_total(): void
    {
        // 11.000 termasuk PPN 11% → DPP 9.910, pajak 1.090
        $calc = (new SaleCalculator)->calculate([['product' => $this->product(11000), 'quantity' => 1]], $this->settings(tax: 1100, inclusive: true));

        $this->assertSame(11000, $calc->grandTotal);
        $this->assertSame(1090, $calc->taxTotal);
    }

    public function test_rounding_modes(): void
    {
        $calculator = new SaleCalculator;
        $lines = [['product' => $this->product(15250), 'quantity' => 1]];

        $nearest = $calculator->calculate($lines, $this->settings(rounding: true));
        $this->assertSame([15300, 50], [$nearest->grandTotal, $nearest->rounding]);

        $down = $calculator->calculate($lines, $this->settings(rounding: true, mode: 'down'));
        $this->assertSame([15200, -50], [$down->grandTotal, $down->rounding]);

        $up = $calculator->calculate([['product' => $this->product(15201), 'quantity' => 1]], $this->settings(rounding: true, mode: 'up'));
        $this->assertSame(15300, $up->grandTotal);
    }

    public function test_tax_allocation_sums_exactly_to_total(): void
    {
        // Tiga baris dengan pembulatan ganjil: jumlah pajak per baris harus sama dengan pajak total
        $calc = (new SaleCalculator)->calculate([
            ['product' => $this->product(3333), 'quantity' => 1],
            ['product' => $this->product(3333), 'quantity' => 1],
            ['product' => $this->product(3334), 'quantity' => 1],
        ], $this->settings(tax: 1100));

        $this->assertSame($calc->taxTotal, array_sum(array_column($calc->lines, 'tax')));
        $this->assertSame(1100, $calc->taxTotal);
    }

    public function test_no_tax_no_service(): void
    {
        $calc = (new SaleCalculator)->calculate([['product' => $this->product(5000, 3000), 'quantity' => 3]], $this->settings());

        $this->assertSame(15000, $calc->grandTotal);
        $this->assertSame(0, $calc->taxTotal);
        $this->assertSame(3000, $calc->lines[0]['cost_price']);
    }
}
