<?php

namespace App\Services\Sales;

use App\Models\Outlet;
use App\Models\Setting;

/**
 * Aturan harga yang berlaku untuk satu transaksi. Tarif dalam basis poin (10% = 1000)
 * agar seluruh hitungan memakai integer.
 */
final readonly class PricingSettings
{
    public function __construct(
        public int $taxBasisPoints,
        public bool $taxInclusive,
        public int $serviceBasisPoints,
        public bool $roundingEnabled,
        public int $roundingTo,
        public string $roundingMode, // nearest | up | down
    ) {}

    public static function forOutlet(Outlet $outlet): self
    {
        $taxEnabled = (bool) Setting::get('tax.enabled', true);
        $serviceEnabled = (bool) Setting::get('service_charge.enabled', false);

        return new self(
            taxBasisPoints: $taxEnabled ? (int) round($outlet->tax_rate * 100) : 0,
            taxInclusive: (bool) Setting::get('tax.inclusive', false),
            serviceBasisPoints: $serviceEnabled ? (int) round($outlet->service_charge_rate * 100) : 0,
            roundingEnabled: (bool) Setting::get('rounding.enabled', false),
            roundingTo: max(1, (int) Setting::get('rounding.to', 100)),
            roundingMode: (string) Setting::get('rounding.mode', 'nearest'),
        );
    }
}
