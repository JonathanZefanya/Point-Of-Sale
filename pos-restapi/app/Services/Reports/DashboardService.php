<?php

namespace App\Services\Reports;

use App\Http\Resources\StockResource;
use App\Services\StockQueryService;

/**
 * Dashboard (FE-1.12): angka hari ini + pembanding hari sebelumnya, grafik per jam,
 * produk terlaris, metode bayar, dan stok menipis.
 */
class DashboardService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly StockQueryService $stock,
    ) {}

    /** @return array<string, mixed> */
    public function build(int $outletId, string $date): array
    {
        $compare = $this->reports->previousDay($date);

        $lowStock = $this->stock->paginate(['outlet_id' => $outletId, 'status' => 'low', 'sort' => 'quantity', 'per_page' => 5]);

        return [
            'date' => $date,
            'compare_date' => $compare,
            'current' => [...$this->reports->metrics($outletId, $date, $date), 'gross_profit' => $this->reports->grossProfit($outletId, $date, $date)],
            'previous' => [...$this->reports->metrics($outletId, $compare, $compare), 'gross_profit' => $this->reports->grossProfit($outletId, $compare, $compare)],
            'hourly' => $this->reports->hourly($outletId, $date),
            'hourly_previous' => $this->reports->hourly($outletId, $compare),
            'top_products' => $this->reports->topProducts($outletId, $date, $date),
            'payments' => $this->reports->payments($outletId, $date, $date),
            'stock' => [
                ...$this->stock->summary($outletId),
                'items' => StockResource::collection($lowStock->items())->resolve(),
            ],
        ];
    }
}
