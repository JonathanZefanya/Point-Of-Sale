<?php

namespace App\Services\Sales;

use App\Models\Product;

/** Hasil SaleCalculator: total transaksi + baris yang siap di-snapshot ke sale_items. */
final readonly class CalculatedSale
{
    /**
     * @param  list<array{product: Product, quantity: int, unit_price: int, cost_price: int, discount: int, tax: int, total: int, note: ?string}>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public int $discountTotal,
        public int $serviceCharge,
        public int $taxTotal,
        public int $rounding,
        public int $grandTotal,
    ) {}

    /** @return array<string, int> */
    public function totals(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'service_charge' => $this->serviceCharge,
            'tax_total' => $this->taxTotal,
            'rounding' => $this->rounding,
            'grand_total' => $this->grandTotal,
        ];
    }
}
