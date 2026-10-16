<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\PointMovement;
use App\Models\Sale;
use App\Models\Setting;

/**
 * Poin loyalty (2.4). Dipanggil di dalam transaksi DB penjualan/void/refund; baris pelanggan dikunci
 * agar poin tidak terpakai dobel oleh dua kasir sekaligus. Saldo poin tidak pernah minus.
 */
class LoyaltyService
{
    public function enabled(): bool
    {
        return (bool) Setting::get('loyalty.enabled', true);
    }

    public function pointValue(): int
    {
        return max(1, (int) Setting::get('loyalty.point_value', 100));
    }

    public function earnPer(): int
    {
        return max(1, (int) Setting::get('loyalty.earn_per', 10000));
    }

    /**
     * Validasi pembayaran poin SEBELUM transaksi disimpan.
     *
     * @param  list<array{method: string, amount: int}>  $payments
     * @return int poin yang akan ditukar
     */
    public function pointsToRedeem(array $payments, ?Customer $customer): int
    {
        $amount = (int) collect($payments)->where('method', PaymentMethod::Points->value)->sum('amount');
        if ($amount === 0) {
            return 0;
        }

        $fail = fn (string $m) => throw new BusinessException($m, 422, ['errors' => ['payments' => [$m]]]);
        if (! $this->enabled()) {
            $fail('Pembayaran dengan poin sedang tidak aktif.');
        }
        if (! $customer) {
            $fail('Pilih pelanggan terlebih dahulu untuk membayar dengan poin.');
        }
        $value = $this->pointValue();
        if ($amount % $value !== 0) {
            $fail('Nominal poin harus kelipatan Rp'.number_format($value, 0, ',', '.').' (nilai 1 poin).');
        }
        $points = intdiv($amount, $value);
        if ($points > $customer->points) {
            $fail("Poin {$customer->name} tidak cukup. Sisa {$customer->points} poin (Rp".number_format($customer->points * $value, 0, ',', '.').').');
        }

        return $points;
    }

    /** Catat penukaran & perolehan poin untuk transaksi yang baru selesai. */
    public function settleSale(Sale $sale, Customer $customer, int $redeemed): void
    {
        if ($redeemed > 0) {
            $this->move($customer, $sale, 'redeem', -$redeemed, "Tukar poin {$sale->number}");
        }

        $pointsPaid = $redeemed * $this->pointValue();
        $earned = $this->enabled() ? intdiv(max(0, $sale->grand_total - $pointsPaid), $this->earnPer()) : 0;
        if ($earned > 0) {
            $this->move($customer, $sale, 'earn', $earned, "Belanja {$sale->number}");
        }

        $sale->update(['points_earned' => $earned, 'points_redeemed' => $redeemed]);
    }

    /** Void: poin yang didapat ditarik, poin yang ditukar dikembalikan. */
    public function reverseSale(Sale $sale): void
    {
        $customer = $sale->customer_id ? Customer::withTrashed()->lockForUpdate()->find($sale->customer_id) : null;
        if (! $customer) {
            return;
        }
        if ($sale->points_redeemed > 0) {
            $this->move($customer, $sale, 'void', $sale->points_redeemed, "Batal {$sale->number}: poin dikembalikan");
        }
        if ($sale->points_earned > 0) {
            $this->move($customer, $sale, 'void', -$sale->points_earned, "Batal {$sale->number}: poin ditarik");
        }
    }

    /**
     * Refund: poin yang didapat ditarik proporsional; refund lewat metode Poin mengembalikan poin.
     */
    public function refund(Sale $sale, int $amount, PaymentMethod $method): void
    {
        $customer = $sale->customer_id ? Customer::withTrashed()->lockForUpdate()->find($sale->customer_id) : null;
        if (! $customer) {
            if ($method === PaymentMethod::Points) {
                throw new BusinessException('Transaksi tanpa pelanggan tidak bisa di-refund dalam bentuk poin.', 422);
            }

            return;
        }

        $clawback = $sale->grand_total > 0 ? intdiv($sale->points_earned * $amount, $sale->grand_total) : 0;
        if ($clawback > 0) {
            $this->move($customer, $sale, 'refund', -$clawback, "Retur {$sale->number}: poin ditarik");
        }
        if ($method === PaymentMethod::Points) {
            $this->move($customer, $sale, 'refund', intdiv($amount, $this->pointValue()), "Retur {$sale->number}: dikembalikan sebagai poin");
        }
    }

    private function move(Customer $customer, Sale $sale, string $type, int $points, string $note): void
    {
        $balance = max(0, $customer->points + $points); // tidak pernah minus
        $customer->update(['points' => $balance]);

        PointMovement::query()->create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => $type,
            'points' => $points,
            'balance_after' => $balance,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }
}
