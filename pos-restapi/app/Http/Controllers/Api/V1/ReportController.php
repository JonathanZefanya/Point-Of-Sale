<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportRequest;
use App\Models\Outlet;
use App\Services\Reports\DashboardService;
use App\Services\Reports\DetailReportService;
use App\Services\Reports\ReportService;
use App\Services\Reports\XlsxExporter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /** Jenis laporan lanjutan → [judul, kolom Excel (key => label)]. */
    public const DETAIL_REPORTS = [
        'products' => ['Laporan Penjualan per Produk', ['name' => 'Produk', 'sku' => 'SKU', 'quantity' => 'Terjual', 'returned' => 'Diretur', 'revenue' => 'Penjualan Bersih', 'cost' => 'Modal', 'profit' => 'Laba Kotor', 'margin' => 'Margin (%)']],
        'categories' => ['Laporan Penjualan per Kategori', ['name' => 'Kategori', 'quantity' => 'Terjual', 'revenue' => 'Penjualan Bersih', 'share' => 'Porsi (%)', 'cost' => 'Modal', 'profit' => 'Laba Kotor', 'margin' => 'Margin (%)']],
        'cashiers' => ['Laporan per Kasir', ['name' => 'Kasir', 'transactions' => 'Transaksi', 'revenue' => 'Penjualan Bersih', 'average' => 'Rata-rata', 'manual_discount' => 'Diskon Manual', 'void_count' => 'Void', 'void_amount' => 'Nilai Void', 'refund_amount' => 'Refund']],
        'stock' => ['Laporan Stok', ['name' => 'Produk', 'sku' => 'SKU', 'quantity' => 'Stok', 'qty_in' => 'Masuk (periode)', 'qty_out' => 'Keluar (periode)', 'cost_price' => 'Harga Modal', 'value' => 'Nilai Persediaan']],
        'profit' => ['Laporan Laba Kotor', ['date' => 'Tanggal', 'revenue' => 'Penjualan Bersih', 'cost' => 'Modal', 'profit' => 'Laba Kotor', 'margin' => 'Margin (%)']],
        'commissions' => ['Laporan Komisi Karyawan', ['name' => 'Karyawan', 'transactions' => 'Transaksi', 'net_sales' => 'Penjualan Bersih', 'percent' => 'Komisi (%)', 'commission' => 'Komisi']],
        'voids' => ['Laporan Void & Refund', ['at' => 'Waktu', 'type_label' => 'Jenis', 'number' => 'No. Transaksi', 'amount' => 'Nominal', 'method' => 'Metode Pengembalian', 'reason' => 'Alasan', 'cashier' => 'Kasir', 'approved_by' => 'Disetujui']],
    ];

    public function __construct(
        private readonly ReportService $reports,
        private readonly XlsxExporter $xlsx,
    ) {}

    public function dashboard(ReportRequest $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->build((int) $request->validated('outlet_id'), $request->validated('date'))]);
    }

    public function sales(ReportRequest $request): Response
    {
        [$outletId, $from, $to] = $this->period($request);
        $report = $this->reports->salesByDay($outletId, $from, $to);

        if ($request->validated('export') === 'xlsx') {
            return $this->xlsx->download(
                "laporan-penjualan-{$from}_{$to}.xlsx",
                $this->titles('Laporan Penjualan', $outletId, $from, $to),
                ['Tanggal', 'Transaksi', 'Penjualan Kotor', 'Diskon', 'Service Charge', 'Pajak', 'Pembulatan', 'Retur', 'Penjualan Bersih'],
                array_map(fn ($d) => [$d['date'], $d['transactions'], $d['gross'], $d['discount'], $d['service_charge'], $d['tax'], $d['rounding'], $d['refunds'], $d['net']], $report['days']),
            );
        }

        return response()->json(['data' => $report['days'], 'summary' => $report['summary']]);
    }

    public function payments(ReportRequest $request): Response
    {
        [$outletId, $from, $to] = $this->period($request);
        $rows = $this->reports->payments($outletId, $from, $to);

        if ($request->validated('export') === 'xlsx') {
            return $this->xlsx->download(
                "laporan-metode-bayar-{$from}_{$to}.xlsx",
                $this->titles('Laporan Metode Pembayaran', $outletId, $from, $to),
                ['Metode', 'Transaksi', 'Nominal'],
                array_map(fn ($r) => [$r['label'], $r['transactions'], $r['amount']], $rows),
            );
        }

        return response()->json(['data' => $rows, 'summary' => ['total' => array_sum(array_column($rows, 'amount'))]]);
    }

    /** Laporan lanjutan (2.11): JSON { data, summary } atau Excel bila ?export=xlsx. */
    public function detail(ReportRequest $request, DetailReportService $detail, string $type): Response
    {
        [$outletId, $from, $to] = $this->period($request);
        [$title, $columns] = self::DETAIL_REPORTS[$type];
        $report = $detail->{$type}($outletId, $from, $to);

        if ($request->validated('export') === 'xlsx') {
            return $this->xlsx->download(
                "laporan-{$type}-{$from}_{$to}.xlsx",
                $this->titles($title, $outletId, $from, $to),
                array_values($columns),
                array_map(fn ($row) => array_map(fn ($key) => $row[$key] ?? null, array_keys($columns)), $report['rows']),
            );
        }

        return response()->json(['data' => $report['rows'], 'summary' => $report['summary']]);
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function period(ReportRequest $request): array
    {
        return [(int) $request->validated('outlet_id'), $request->validated('from'), $request->validated('to')];
    }

    /** @return list<string> */
    private function titles(string $name, int $outletId, string $from, string $to): array
    {
        $outlet = Outlet::query()->findOrFail($outletId);

        return [$name, "Outlet: {$outlet->code} · {$outlet->name}", "Periode: {$from} s.d. {$to}"];
    }
}
