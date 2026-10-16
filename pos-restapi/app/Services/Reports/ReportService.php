<?php

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\SaleRefundItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Laporan dasar (BE-1.11). Omzet = transaksi berstatus selesai (void tidak dihitung).
 * Semua tanggal memakai zona waktu aplikasi (Asia/Jakarta).
 */
class ReportService
{
    /** Transaksi selesai di outlet dalam rentang tanggal (inklusif). */
    public function completedSales(int $outletId, string $from, string $to): Builder
    {
        // Transaksi yang di-refund tetap dihitung di tanggal jualnya; refund dikurangkan di tanggal refund
        return Sale::query()
            ->where('outlet_id', $outletId)
            ->whereIn('status', [SaleStatus::Completed, SaleStatus::PartialRefund, SaleStatus::Refunded])
            ->whereBetween('completed_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    }

    /** Refund yang dibayarkan dalam rentang tanggal (berdasarkan tanggal refund). */
    public function refundsIn(int $outletId, string $from, string $to): Builder
    {
        return SaleRefund::query()
            ->where('outlet_id', $outletId)
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    }

    /** @return array{revenue: int, transactions: int, average: int} */
    public function metrics(int $outletId, string $from, string $to): array
    {
        $row = $this->completedSales($outletId, $from, $to)
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(grand_total), 0) AS revenue')
            ->toBase()
            ->first();
        $count = (int) $row->c;
        $revenue = (int) $row->revenue - (int) $this->refundsIn($outletId, $from, $to)->sum('amount');

        return [
            'revenue' => $revenue,
            'transactions' => $count,
            'average' => $count ? intdiv($revenue, $count) : 0,
        ];
    }

    /** Laba kotor = penjualan bersih item (tanpa pajak/service) − harga modal snapshot. */
    public function grossProfit(int $outletId, string $from, string $to): int
    {
        $sold = (int) SaleItem::query()
            ->whereIn('sale_id', $this->completedSales($outletId, $from, $to)->select('id'))
            ->sum(DB::raw('total - cost_price * quantity'));

        // Barang yang diretur mengurangi laba sebesar (nilai jual − modal) porsinya
        $returned = (int) SaleRefundItem::query()
            ->whereIn('refund_id', $this->refundsIn($outletId, $from, $to)->select('id'))
            ->sum(DB::raw('line_total - cost_total'));

        return $sold - $returned;
    }

    /** @return list<array{hour: int, revenue: int, transactions: int}> 24 jam, termasuk jam tanpa transaksi */
    public function hourly(int $outletId, string $date): array
    {
        $hours = array_fill(0, 24, ['revenue' => 0, 'transactions' => 0]);
        $sales = $this->completedSales($outletId, $date, $date)->get(['completed_at', 'grand_total']);

        foreach ($sales as $sale) {
            $h = (int) $sale->completed_at->format('G');
            $hours[$h]['revenue'] += $sale->grand_total;
            $hours[$h]['transactions']++;
        }

        return array_map(fn ($v, $h) => ['hour' => $h, ...$v], $hours, array_keys($hours));
    }

    /** @return list<array{product_id: int, name: string, quantity: int, revenue: int}> */
    public function topProducts(int $outletId, string $from, string $to, int $limit = 5): array
    {
        return SaleItem::query()
            ->whereIn('sale_id', $this->completedSales($outletId, $from, $to)->select('id'))
            ->selectRaw('product_id, MAX(product_name) AS name, SUM(quantity) AS qty, SUM(total) AS revenue')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'name' => $r->name, 'quantity' => (int) $r->qty, 'revenue' => (int) $r->revenue])
            ->all();
    }

    /**
     * Per metode bayar. Tunai dihitung bersih (dikurangi kembalian) = uang yang benar-benar masuk.
     *
     * @return list<array{method: string, label: string, transactions: int, amount: int}>
     */
    public function payments(int $outletId, string $from, string $to): array
    {
        $sales = $this->completedSales($outletId, $from, $to);
        $rows = SalePayment::query()
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->selectRaw('method, COUNT(DISTINCT sale_id) AS tx, SUM(amount) AS amount')
            ->groupBy('method')
            ->toBase()
            ->get()
            ->keyBy('method');
        $change = (int) (clone $sales)->sum('change_amount');
        $refunds = $this->refundsIn($outletId, $from, $to)->selectRaw('method, SUM(amount) AS total')->groupBy('method')->pluck('total', 'method');

        $result = [];
        foreach (PaymentMethod::cases() as $method) {
            $row = $rows->get($method->value);
            if (! $row && ! isset($refunds[$method->value])) {
                continue;
            }
            // Uang yang benar-benar diterima: dibayar − kembalian (tunai) − dikembalikan lewat refund
            $amount = (int) ($row->amount ?? 0) - ($method->isCash() ? $change : 0) - (int) ($refunds[$method->value] ?? 0);
            $result[] = ['method' => $method->value, 'label' => $method->label(), 'transactions' => (int) ($row->tx ?? 0), 'amount' => $amount];
        }

        return $result;
    }

    /**
     * Ringkasan penjualan + baris per hari.
     *
     * @return array{summary: array<string, int>, days: list<array<string, mixed>>}
     */
    public function salesByDay(int $outletId, string $from, string $to): array
    {
        $columns = 'COUNT(*) AS transactions, COALESCE(SUM(subtotal),0) AS gross, COALESCE(SUM(discount_total),0) AS discount, '
            .'COALESCE(SUM(service_charge),0) AS service_charge, COALESCE(SUM(tax_total),0) AS tax, '
            .'COALESCE(SUM(rounding),0) AS rounding, COALESCE(SUM(grand_total),0) AS net';

        $days = $this->completedSales($outletId, $from, $to)
            ->selectRaw("DATE(completed_at) AS day, {$columns}")
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->orderBy('day')
            ->toBase()
            ->get()
            ->keyBy('day');

        // Refund per tanggal refund → kolom "Retur"; penjualan bersih = penjualan − retur
        $refundDays = $this->refundsIn($outletId, $from, $to)
            ->selectRaw('DATE(created_at) AS day, SUM(amount) AS total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $keys = ['transactions', 'gross', 'discount', 'service_charge', 'tax', 'rounding', 'net'];
        $dates = collect($days->keys())->merge($refundDays->keys())->map(fn ($d) => (string) $d)->unique()->sort()->values();
        $days = $dates->map(function (string $date) use ($days, $refundDays, $keys) {
            $row = $days->get($date);
            $values = $row ? $this->ints($row, $keys) : array_fill_keys($keys, 0);
            $values['refunds'] = (int) ($refundDays[$date] ?? 0);
            $values['net'] -= $values['refunds'];

            return ['date' => $date, ...$values];
        })->all();

        $total = $this->completedSales($outletId, $from, $to)->selectRaw($columns)->toBase()->first();
        $summary = $this->ints($total, $keys);
        $summary['refunds'] = (int) $refundDays->sum();
        $summary['net'] -= $summary['refunds'];
        $summary['average'] = $summary['transactions'] ? intdiv($summary['net'], $summary['transactions']) : 0;

        $void = Sale::query()->where('outlet_id', $outletId)->where('status', SaleStatus::Void)
            ->whereBetween('completed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(grand_total),0) AS amount')->toBase()->first();
        $summary['void_count'] = (int) $void->c;
        $summary['void_amount'] = (int) $void->amount;

        return ['summary' => $summary, 'days' => $days];
    }

    /** Tanggal pembanding: hari sebelumnya (label di frontend: "vs kemarin"). */
    public function previousDay(string $date): string
    {
        return CarbonImmutable::parse($date)->subDay()->toDateString();
    }

    private function ints(object $row, array $keys): array
    {
        return array_combine($keys, array_map(fn ($k) => (int) $row->{$k}, $keys));
    }
}
