<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\Shift;

/**
 * Ringkasan penjualan dalam satu shift: per metode bayar, jumlah transaksi, tunai bersih.
 * Transaksi void tidak dihitung (uangnya sudah dikembalikan saat void).
 */
class ShiftSalesSummary
{
    /** Tunai yang masuk laci dari penjualan: pembayaran tunai − kembalian. */
    public function netCash(Shift $shift): int
    {
        $sales = $this->countedSales($shift);
        $cashPaid = (int) SalePayment::query()
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->where('method', PaymentMethod::Cash)
            ->sum('amount');

        // Refund tunai yang dibayarkan dari laci shift ini mengurangi kas
        $cashRefunds = (int) SaleRefund::query()->where('shift_id', $shift->id)->where('method', PaymentMethod::Cash)->sum('amount');

        return $cashPaid - (int) (clone $sales)->sum('change_amount') - $cashRefunds;
    }

    /**
     * @return array{transaction_count: int, sales_total: int, payments: list<array{method: string, label: string, amount: int}>}
     */
    public function summary(Shift $shift): array
    {
        $sales = $this->countedSales($shift);

        $byMethod = SalePayment::query()
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->selectRaw('method, SUM(amount) AS total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $payments = [];
        foreach (PaymentMethod::cases() as $method) {
            $amount = (int) ($byMethod[$method->value] ?? 0);
            if ($method->isCash()) {
                // tunai bersih kembalian (refund ditampilkan terpisah)
                $amount = $amount - (int) (clone $sales)->sum('change_amount');
            }
            if ($amount > 0) {
                $payments[] = ['method' => $method->value, 'label' => $method->label(), 'amount' => $amount];
            }
        }

        $refunds = SaleRefund::query()->where('shift_id', $shift->id)
            ->selectRaw('method, SUM(amount) AS total')->groupBy('method')->pluck('total', 'method');

        return [
            'refunds' => collect(PaymentMethod::cases())
                ->filter(fn ($m) => isset($refunds[$m->value]))
                ->map(fn ($m) => ['method' => $m->value, 'label' => $m->label(), 'amount' => (int) $refunds[$m->value]])
                ->values()->all(),
            'refund_total' => (int) $refunds->sum(),
            'transaction_count' => (clone $sales)->count(),
            'sales_total' => (int) (clone $sales)->sum('grand_total'),
            'payments' => $payments,
        ];
    }

    private function countedSales(Shift $shift)
    {
        return Sale::query()
            ->where('shift_id', $shift->id)
            ->whereIn('status', [SaleStatus::Completed, SaleStatus::PartialRefund, SaleStatus::Refunded]);
    }
}
