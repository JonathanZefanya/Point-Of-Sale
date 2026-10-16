<?php

namespace Tests\Feature\Report;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private User $manager;

    private Product $kopi;

    private Product $roti;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);

        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->cashier = User::factory()->cashier()->create();
        $this->manager = User::factory()->manager()->create(['pin' => '999999']);
        $this->cashier->outlets()->attach($this->outlet);
        $this->manager->outlets()->attach($this->outlet);

        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000, 'cost_price' => 6000, 'min_stock' => 5]);
        $this->roti = Product::factory()->create(['name' => 'Roti', 'sell_price' => 10000, 'cost_price' => 4000, 'min_stock' => 5]);
        $stock = app(StockService::class);
        $stock->change($this->outlet->id, $this->kopi, 100, StockMovementType::Initial);
        $stock->change($this->outlet->id, $this->roti, 8, StockMovementType::Initial);

        // Kemarin 15:00 → 1 transaksi Rp15.000
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(15, 0));
        $this->openShift();
        $this->sell([[$this->kopi, 1]], [['method' => 'cash', 'amount' => 20000]]);

        // Hari ini 09:30 & 10:15 → Rp40.000 (2 kopi + 1 roti, QRIS) dan Rp15.000 tunai (kembalian 5.000)
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 30));
        $this->sell([[$this->kopi, 2], [$this->roti, 1]], [['method' => 'qris', 'amount' => 40000]]);
        $this->travelTo(now()->setTime(10, 15));
        $this->sell([[$this->kopi, 1]], [['method' => 'cash', 'amount' => 20000]]);
    }

    private function openShift(): void
    {
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
    }

    private function sell(array $lines, array $payments): int
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => array_map(fn ($l) => ['product_id' => $l[0]->id, 'quantity' => $l[1]], $lines),
            'payments' => $payments,
        ])->assertCreated()->json('data.id');
    }

    public function test_dashboard_today_vs_yesterday(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/reports/dashboard')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-15')
            ->assertJsonPath('data.compare_date', '2026-10-14')
            ->assertJsonPath('data.current.revenue', 55000)
            ->assertJsonPath('data.current.transactions', 2)
            ->assertJsonPath('data.current.average', 27500)
            ->assertJsonPath('data.current.gross_profit', 55000 - (3 * 6000 + 4000))
            ->assertJsonPath('data.previous.revenue', 15000)
            ->assertJsonPath('data.hourly.9.revenue', 40000)
            ->assertJsonPath('data.hourly.10.transactions', 1)
            ->assertJsonPath('data.hourly_previous.15.revenue', 15000)
            ->assertJsonPath('data.top_products.0.name', 'Kopi')
            ->assertJsonPath('data.top_products.0.quantity', 3)
            ->assertJsonPath('data.stock.low', 0);
    }

    public function test_dashboard_lists_low_stock(): void
    {
        $this->sell([[$this->roti, 4]], [['method' => 'cash', 'amount' => 40000]]); // roti sisa 3 ≤ minimum 5

        $this->actingAs($this->manager)->getJson('/api/v1/reports/dashboard')
            ->assertJsonPath('data.stock.low', 1)
            ->assertJsonPath('data.stock.items.0.name', 'Roti')
            ->assertJsonPath('data.stock.items.0.quantity', 3);
    }

    public function test_void_is_excluded_from_revenue_and_counted_separately(): void
    {
        $id = $this->sell([[$this->roti, 1]], [['method' => 'cash', 'amount' => 10000]]);
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$id}/void", ['reason' => 'Batal', 'supervisor_pin' => '999999'])->assertOk();

        $this->actingAs($this->manager)->getJson('/api/v1/reports/sales')
            ->assertJsonPath('summary.net', 55000)
            ->assertJsonPath('summary.void_count', 1)
            ->assertJsonPath('summary.void_amount', 10000);
    }

    public function test_sales_report_per_day_with_period(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/reports/sales?from=2026-10-14&to=2026-10-15')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-10-14')
            ->assertJsonPath('data.0.net', 15000)
            ->assertJsonPath('data.1.transactions', 2)
            ->assertJsonPath('summary.net', 70000)
            ->assertJsonPath('summary.transactions', 3)
            ->assertJsonPath('summary.average', 23333);
    }

    public function test_payments_report_cash_is_net_of_change(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/reports/payments?from=2026-10-14&to=2026-10-15')
            ->assertOk()
            ->assertJsonPath('data', [
                ['method' => 'cash', 'label' => 'Tunai', 'transactions' => 2, 'amount' => 30000],
                ['method' => 'qris', 'label' => 'QRIS', 'transactions' => 1, 'amount' => 40000],
            ])
            ->assertJsonPath('summary.total', 70000);
    }

    public function test_export_xlsx(): void
    {
        $response = $this->actingAs($this->manager)->get('/api/v1/reports/sales?from=2026-10-14&to=2026-10-15&export=xlsx');

        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('laporan-penjualan-2026-10-14_2026-10-15.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', file_get_contents($response->getFile()->getPathname())); // file zip/xlsx
    }

    public function test_access_and_validation(): void
    {
        $this->actingAs($this->cashier)->getJson('/api/v1/reports/dashboard')->assertStatus(403);
        $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/sales?from=2024-01-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonPath('errors.to.0', 'Rentang laporan maksimal 1 tahun. Persempit periodenya.');
    }
}
