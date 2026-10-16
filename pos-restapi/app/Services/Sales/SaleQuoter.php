<?php

namespace App\Services\Sales;

use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\Outlet;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;

/**
 * Hitung total transaksi lengkap: baris (varian/modifier) → promo/voucher/diskon manual → pajak & pembulatan.
 * Dipakai POST /sales/quote (tampilan kasir) dan saat checkout (sumber kebenaran).
 */
class SaleQuoter
{
    public function __construct(
        private readonly SaleLineResolver $lines,
        private readonly PromotionEngine $promotions,
        private readonly SaleCalculator $calculator,
    ) {}

    /**
     * @param  array{type: string, value: int}|null  $manual
     * @return array{calc: CalculatedSale, applied: list<array<string, mixed>>, manual_discount: int, voucher: ?Voucher, needs_supervisor: bool}
     */
    public function quote(Outlet $outlet, array $items, User $user, ?string $voucherCode = null, ?array $manual = null, bool $lockVoucher = false): array
    {
        $now = now();
        $voucher = $voucherCode ? $this->voucher($voucherCode, $outlet->id, $lockVoucher) : null;
        $result = $this->promotions->apply($this->lines->resolve($items, $outlet->id), $outlet->id, $now, $voucher, $manual);
        $calc = $this->calculator->calculate($result['lines'], PricingSettings::forOutlet($outlet));

        $base = $calc->subtotal - ($calc->discountTotal - $result['manual_discount']); // nilai sebelum diskon manual

        return [
            'calc' => $calc,
            'applied' => $result['applied'],
            'manual_discount' => $result['manual_discount'],
            'voucher' => $voucher,
            'needs_supervisor' => $this->needsSupervisor($user, $result['manual_discount'], $base),
        ];
    }

    /** Diskon manual kasir di atas batas (Pengaturan) butuh PIN pemilik/manajer; pemilik/manajer tidak dibatasi. */
    public function needsSupervisor(User $user, int $manualDiscount, int $base): bool
    {
        if ($manualDiscount <= 0 || $user->role->canAuthorize() || $user->role !== Role::Cashier) {
            return false;
        }
        $limit = (int) Setting::get('discount.manual_limit_percent', 10);

        return $base > 0 && $manualDiscount * 100 > $base * $limit;
    }

    /** Voucher harus ada, promonya berjalan & berlaku di outlet/jam ini, dan kuota belum habis. */
    public function voucher(string $code, int $outletId, bool $lock = false): Voucher
    {
        $query = Voucher::query()->where('code', strtoupper(trim($code)))->with('promotion');
        $voucher = $lock ? $query->lockForUpdate()->first() : $query->first();
        $fail = fn (string $m) => throw new BusinessException($m, 422, ['errors' => ['voucher_code' => [$m]]]);

        if (! $voucher || ! $voucher->promotion) {
            $fail("Kode voucher {$code} tidak ditemukan. Periksa kembali kodenya.");
        }
        $promo = $voucher->promotion;
        $running = Promotion::query()->running(now())->whereKey($promo->id)->exists();
        if (! $running || ! $promo->appliesAt($outletId, now())) {
            $fail("Voucher {$voucher->code} tidak berlaku saat ini atau di outlet ini.");
        }
        if ($voucher->exhausted()) {
            $fail("Kuota voucher {$voucher->code} sudah habis.");
        }

        return $voucher;
    }
}
