<?php

namespace App\Services\Reports;

use App\Enums\SaleStatus;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleRefundItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Laporan lanjutan (2.11): per produk, kategori, kasir, stok, laba kotor, void/refund.
 * Semua angka dari snapshot transaksi (sale_items), bukan data produk saat ini.
 * Retur dikurangkan di tanggal refund (sama dengan laporan penjualan).
 */
class DetailReportService
{
    public function __construct(private readonly ReportService $base) {}

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, int>} */
    public function products(int $outletId, string $from, string $to): array
    {
        $sold = $this->soldItems($outletId, $from, $to)
            ->selectRaw('product_id, variant_id, MAX(product_name) AS name, MAX(sku) AS sku, SUM(quantity) AS qty, SUM(total) AS revenue, SUM(cost_price * quantity) AS cost')
            ->groupBy('product_id', 'variant_id')->toBase()->get()
            ->keyBy(fn ($r) => $r->product_id.':'.($r->variant_id ?? 0));
        $returned = $this->returnedItems($outletId, $from, $to)
            ->selectRaw('sale_items.product_id, sale_items.variant_id, MAX(sale_items.product_name) AS name, MAX(sale_items.sku) AS sku, SUM(sale_refund_items.quantity) AS qty, SUM(line_total) AS revenue, SUM(cost_total) AS cost')
            ->groupBy('sale_items.product_id', 'sale_items.variant_id')->toBase()->get()
            ->keyBy(fn ($r) => $r->product_id.':'.($r->variant_id ?? 0));

        $rows = $sold->keys()->merge($returned->keys())->unique()->map(function ($key) use ($sold, $returned) {
            $s = $sold->get($key);
            $r = $returned->get($key);

            return $this->profitRow([
                'name' => $s?->name ?? $r?->name,
                'sku' => $s?->sku ?? $r?->sku,
                'quantity' => (int) ($s->qty ?? 0),
                'returned' => (int) ($r->qty ?? 0),
            ], (int) ($s->revenue ?? 0) - (int) ($r->revenue ?? 0), (int) ($s->cost ?? 0) - (int) ($r->cost ?? 0));
        })->sortByDesc('revenue')->values()->all();

        return ['rows' => $rows, 'summary' => $this->totals($rows, ['quantity', 'returned', 'revenue', 'cost', 'profit'])];
    }

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, int>} */
    public function categories(int $outletId, string $from, string $to): array
    {
        $sold = $this->soldItems($outletId, $from, $to)
            ->selectRaw('category_id, MAX(category_name) AS name, SUM(quantity) AS qty, SUM(total) AS revenue, SUM(cost_price * quantity) AS cost')
            ->groupBy('category_id')->toBase()->get()->keyBy(fn ($r) => (int) $r->category_id);
        $returned = $this->returnedItems($outletId, $from, $to)
            ->selectRaw('sale_items.category_id, MAX(sale_items.category_name) AS name, SUM(sale_refund_items.quantity) AS qty, SUM(line_total) AS revenue, SUM(cost_total) AS cost')
            ->groupBy('sale_items.category_id')->toBase()->get()->keyBy(fn ($r) => (int) $r->category_id);

        $rows = $sold->keys()->merge($returned->keys())->unique()->map(function ($key) use ($sold, $returned) {
            $s = $sold->get($key);
            $r = $returned->get($key);

            return $this->profitRow([
                'name' => ($s?->name ?? $r?->name) ?: 'Tanpa kategori',
                'quantity' => (int) ($s->qty ?? 0) - (int) ($r->qty ?? 0),
            ], (int) ($s->revenue ?? 0) - (int) ($r->revenue ?? 0), (int) ($s->cost ?? 0) - (int) ($r->cost ?? 0));
        })->sortByDesc('revenue')->values()->all();

        $summary = $this->totals($rows, ['quantity', 'revenue', 'cost', 'profit']);
        foreach ($rows as $i => $row) {
            $rows[$i]['share'] = $summary['revenue'] > 0 ? round($row['revenue'] * 100 / $summary['revenue'], 1) : 0;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /** Kinerja per kasir: transaksi, omzet, rata-rata, diskon manual, void, refund yang diproses. */
    public function cashiers(int $outletId, string $from, string $to): array
    {
        $sales = $this->base->completedSales($outletId, $from, $to)
            ->selectRaw('user_id, COUNT(*) AS tx, SUM(grand_total) AS revenue, SUM(manual_discount) AS manual_discount')
            ->groupBy('user_id')->toBase()->get()->keyBy('user_id');
        $voids = Sale::query()->where('outlet_id', $outletId)->where('status', SaleStatus::Void)
            ->whereBetween('completed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->selectRaw('user_id, COUNT(*) AS c, SUM(grand_total) AS amount')->groupBy('user_id')->toBase()->get()->keyBy('user_id');
        $refunds = $this->base->refundsIn($outletId, $from, $to)
            ->selectRaw('user_id, COUNT(*) AS c, SUM(amount) AS amount')->groupBy('user_id')->toBase()->get()->keyBy('user_id');
        $ids = $sales->keys()->merge($voids->keys())->merge($refunds->keys())->unique();
        $names = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');

        $rows = $ids->map(function ($id) use ($sales, $voids, $refunds, $names) {
            $tx = (int) ($sales[$id]->tx ?? 0);
            $revenue = (int) ($sales[$id]->revenue ?? 0) - (int) ($refunds[$id]->amount ?? 0);

            return [
                'name' => $names[$id] ?? 'Pengguna terhapus',
                'transactions' => $tx,
                'revenue' => $revenue,
                'average' => $tx ? intdiv($revenue, $tx) : 0,
                'manual_discount' => (int) ($sales[$id]->manual_discount ?? 0),
                'void_count' => (int) ($voids[$id]->c ?? 0),
                'void_amount' => (int) ($voids[$id]->amount ?? 0),
                'refund_amount' => (int) ($refunds[$id]->amount ?? 0),
            ];
        })->sortByDesc('revenue')->values()->all();

        return ['rows' => $rows, 'summary' => $this->totals($rows, ['transactions', 'revenue', 'manual_discount', 'void_count', 'void_amount', 'refund_amount'])];
    }

    /** Stok saat ini + nilai persediaan (qty × modal) + mutasi masuk/keluar dalam periode. */
    public function stock(int $outletId, string $from, string $to): array
    {
        $moves = StockMovement::query()->where('outlet_id', $outletId)
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->selectRaw('product_id, variant_id, SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END) AS qty_in, SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END) AS qty_out')
            ->groupBy('product_id', 'variant_id')->toBase()->get()
            ->keyBy(fn ($r) => $r->product_id.':'.($r->variant_id ?? 0));

        $rows = ProductStock::query()->where('product_stocks.outlet_id', $outletId)
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'product_stocks.variant_id')
            ->whereNull('products.deleted_at')
            ->orderBy('products.name')->orderBy('product_variants.name')
            ->get([
                'product_stocks.product_id', 'product_stocks.variant_id', 'product_stocks.quantity', 'products.min_stock',
                'products.name', 'products.sku', 'products.cost_price', 'product_variants.name AS variant_name',
                'product_variants.sku AS variant_sku', 'product_variants.cost_price AS variant_cost',
            ])
            ->map(function ($s) use ($moves) {
                $cost = $s->variant_id ? ((int) $s->variant_cost ?: (int) $s->cost_price) : (int) $s->cost_price;
                $m = $moves->get($s->product_id.':'.($s->variant_id ?? 0));

                return [
                    'name' => $s->variant_name ? "{$s->name} - {$s->variant_name}" : $s->name,
                    'sku' => $s->variant_sku ?? $s->sku,
                    'quantity' => (int) $s->quantity,
                    'qty_in' => (int) ($m->qty_in ?? 0),
                    'qty_out' => (int) ($m->qty_out ?? 0),
                    'cost_price' => $cost,
                    'value' => max(0, (int) $s->quantity) * $cost,
                    'status' => $s->quantity <= 0 ? 'out' : ($s->quantity <= $s->min_stock ? 'low' : 'ok'),
                ];
            })->all();

        $summary = $this->totals($rows, ['quantity', 'qty_in', 'qty_out', 'value']);
        $summary['low'] = count(array_filter($rows, fn ($r) => $r['status'] === 'low'));
        $summary['out'] = count(array_filter($rows, fn ($r) => $r['status'] === 'out'));

        return ['rows' => $rows, 'summary' => $summary];
    }

    /** Laba kotor per hari: penjualan bersih item − modal (retur dikurangkan di tanggal refund). */
    public function profit(int $outletId, string $from, string $to): array
    {
        $sold = $this->soldItems($outletId, $from, $to)
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('DATE(sales.completed_at) AS day, SUM(sale_items.total) AS revenue, SUM(sale_items.cost_price * sale_items.quantity) AS cost')
            ->groupBy(DB::raw('DATE(sales.completed_at)'))->toBase()->get()->keyBy(fn ($r) => (string) $r->day);
        $returned = $this->returnedItems($outletId, $from, $to)
            ->selectRaw('DATE(sale_refunds.created_at) AS day, SUM(line_total) AS revenue, SUM(cost_total) AS cost')
            ->groupBy(DB::raw('DATE(sale_refunds.created_at)'))->toBase()->get()->keyBy(fn ($r) => (string) $r->day);

        $rows = $sold->keys()->merge($returned->keys())->unique()->sort()->values()->map(function ($day) use ($sold, $returned) {
            return $this->profitRow(['date' => $day],
                (int) ($sold[$day]->revenue ?? 0) - (int) ($returned[$day]->revenue ?? 0),
                (int) ($sold[$day]->cost ?? 0) - (int) ($returned[$day]->cost ?? 0));
        })->all();

        $summary = $this->totals($rows, ['revenue', 'cost', 'profit']);
        $summary['margin'] = $summary['revenue'] > 0 ? round($summary['profit'] * 100 / $summary['revenue'], 1) : 0;

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * Komisi karyawan (3.7): persen komisi × penjualan bersih item (tanpa pajak/service) yang dicatat karyawan itu.
     * Retur dikurangkan dari penjual aslinya pada tanggal refund. Persen komisi = pengaturan karyawan saat ini.
     */
    public function commissions(int $outletId, string $from, string $to): array
    {
        $sold = $this->soldItems($outletId, $from, $to)->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sales.user_id, COUNT(DISTINCT sales.id) AS tx, SUM(sale_items.total) AS revenue')
            ->groupBy('sales.user_id')->toBase()->get()->keyBy('user_id');
        $returned = $this->returnedItems($outletId, $from, $to)->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sales.user_id, SUM(line_total) AS amount')->groupBy('sales.user_id')->toBase()->get()->keyBy('user_id');
        $ids = $sold->keys()->merge($returned->keys())->unique();
        $users = DB::table('users')->whereIn('id', $ids)->get(['id', 'name', 'commission_percent'])->keyBy('id');

        $rows = $ids->map(function ($id) use ($sold, $returned, $users) {
            $net = (int) ($sold[$id]->revenue ?? 0) - (int) ($returned[$id]->amount ?? 0);
            $percent = (float) ($users[$id]->commission_percent ?? 0);

            return [
                'name' => $users[$id]->name ?? 'Pengguna terhapus',
                'transactions' => (int) ($sold[$id]->tx ?? 0),
                'net_sales' => $net,
                'percent' => $percent,
                'commission' => (int) round(max(0, $net) * $percent / 100),
            ];
        })->sortByDesc('commission')->values()->all();

        return ['rows' => $rows, 'summary' => $this->totals($rows, ['transactions', 'net_sales', 'commission'])];
    }

    /** Daftar void & refund dalam periode, terbaru di atas. */
    public function voids(int $outletId, string $from, string $to): array
    {
        $voids = Sale::query()->with('user', 'voider')->where('outlet_id', $outletId)->where('status', SaleStatus::Void)
            ->whereBetween('voided_at', ["{$from} 00:00:00", "{$to} 23:59:59"])->get()
            ->map(fn (Sale $s) => [
                'type' => 'void', 'type_label' => 'Void', 'at' => $s->voided_at?->toIso8601String(), 'number' => $s->number,
                'amount' => $s->grand_total, 'reason' => $s->void_reason, 'cashier' => $s->user?->name, 'approved_by' => $s->voider?->name, 'method' => null,
            ]);
        $refunds = $this->base->refundsIn($outletId, $from, $to)->with('sale', 'user', 'approver')->get()
            ->map(fn ($r) => [
                'type' => 'refund', 'type_label' => 'Refund', 'at' => $r->created_at?->toIso8601String(), 'number' => $r->sale->number,
                'amount' => $r->amount, 'reason' => $r->reason, 'cashier' => $r->user?->name, 'approved_by' => $r->approver?->name, 'method' => $r->method->label(),
            ]);
        $rows = $voids->toBase()->merge($refunds)->sortByDesc('at')->values()->all();

        return ['rows' => $rows, 'summary' => [
            'void_count' => $voids->count(), 'void_amount' => (int) $voids->sum('amount'),
            'refund_count' => $refunds->count(), 'refund_amount' => (int) $refunds->sum('amount'),
        ]];
    }

    private function soldItems(int $outletId, string $from, string $to)
    {
        return SaleItem::query()->whereIn('sale_items.sale_id', $this->base->completedSales($outletId, $from, $to)->select('id'));
    }

    private function returnedItems(int $outletId, string $from, string $to)
    {
        return SaleRefundItem::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_refund_items.sale_item_id')
            ->join('sale_refunds', 'sale_refunds.id', '=', 'sale_refund_items.refund_id')
            ->where('sale_refunds.outlet_id', $outletId)
            ->whereBetween('sale_refunds.created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
    }

    private function profitRow(array $row, int $revenue, int $cost): array
    {
        $profit = $revenue - $cost;

        return [...$row, 'revenue' => $revenue, 'cost' => $cost, 'profit' => $profit, 'margin' => $revenue > 0 ? round($profit * 100 / $revenue, 1) : 0];
    }

    private function totals(array $rows, array $keys): array
    {
        return array_combine($keys, array_map(fn ($k) => (int) array_sum(array_column($rows, $k)), $keys));
    }
}
