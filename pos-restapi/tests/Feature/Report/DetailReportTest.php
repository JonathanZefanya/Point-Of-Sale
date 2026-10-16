<?php

namespace Tests\Feature\Report;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetailReportTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $owner;

    private User $cashier;

    private Product $kopi;

    private Product $roti;

    private array $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        Setting::put('tax.enabled', false);
        $this->outlet = Outlet::factory()->create();
        $this->owner = User::factory()->owner()->create();
        $this->cashier = User::factory()->cashier()->create(['name' => 'Rina']);
        $this->cashier->outlets()->attach($this->outlet);
        $manager = User::factory()->manager()->create(['pin' => '999999']);
        $manager->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);

        $minuman = Category::query()->create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sku' => 'KOPI', 'category_id' => $minuman->id, 'sell_price' => 15000, 'cost_price' => 7000, 'min_stock' => 5]);
        $this->roti = Product::factory()->create(['name' => 'Roti', 'sku' => 'ROTI', 'category_id' => null, 'sell_price' => 18000, 'cost_price' => 9000, 'min_stock' => 0]);
        $stock = app(StockService::class);
        $stock->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
        $stock->change($this->outlet->id, $this->roti, 3, StockMovementType::Initial);

        // Kopi ×2 (30.000, modal 14.000) + Roti ×1 (18.000, modal 9.000)
        $this->sale = $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2], ['product_id' => $this->roti->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ])->assertCreated()->json('data');

        // Kategori produk diganti setelah transaksi: laporan tetap memakai snapshot
        $this->kopi->update(['category_id' => Category::query()->create(['name' => 'Lain', 'sort_order' => 2])->id]);
    }

    private function report(string $type, array $query = [])
    {
        return $this->actingAs($this->owner)->getJson("/api/v1/reports/{$type}?".http_build_query(['outlet_id' => $this->outlet->id, ...$query]));
    }

    private function refundOneKopi(): void
    {
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$this->sale['id']}/refund", [
            'items' => [['sale_item_id' => $this->sale['items'][0]['id'], 'quantity' => 1]],
            'method' => 'cash', 'reason' => 'Kopi tumpah', 'supervisor_pin' => '999999',
        ])->assertOk();
    }

    public function test_products_report_with_returns_and_profit(): void
    {
        $this->refundOneKopi();
        $rows = collect($this->report('products')->assertOk()->json('data'))->keyBy('sku');

        $this->assertSame(['quantity' => 2, 'returned' => 1, 'revenue' => 15000, 'cost' => 7000, 'profit' => 8000], array_intersect_key($rows['KOPI'], array_flip(['quantity', 'returned', 'revenue', 'cost', 'profit'])));
        $this->assertSame(9000, $rows['ROTI']['profit']);
        $this->assertEquals(50, $rows['ROTI']['margin']);
        $this->report('products')->assertJsonPath('summary.profit', 17000);
    }

    public function test_categories_use_snapshot_and_share(): void
    {
        $data = $this->report('categories')->assertOk()->json();
        $rows = collect($data['data'])->keyBy('name');

        $this->assertSame(30000, $rows['Minuman']['revenue']);
        $this->assertSame(18000, $rows['Tanpa kategori']['revenue']);
        $this->assertEquals(62.5, $rows['Minuman']['share']);
        $this->assertArrayNotHasKey('Lain', $rows->all());
        $this->assertSame(48000, $data['summary']['revenue']);
    }

    public function test_cashiers_profit_stock_and_voids(): void
    {
        $this->refundOneKopi();

        $this->report('cashiers')->assertOk()
            ->assertJsonPath('data.0.name', 'Rina')->assertJsonPath('data.0.transactions', 1)
            ->assertJsonPath('data.0.revenue', 48000 - 15000)->assertJsonPath('data.0.refund_amount', 15000);

        $this->report('profit')->assertOk()
            ->assertJsonPath('data.0.date', '2026-10-15')->assertJsonPath('data.0.revenue', 33000)
            ->assertJsonPath('data.0.profit', 17000);

        $stock = collect($this->report('stock')->assertOk()->json('data'))->keyBy('sku');
        $this->assertSame(['quantity' => 9, 'qty_in' => 11, 'qty_out' => 2, 'value' => 9 * 7000], array_intersect_key($stock['KOPI'], array_flip(['quantity', 'qty_in', 'qty_out', 'value'])));
        $this->assertSame('ok', $stock['KOPI']['status']);

        $this->report('voids')->assertOk()->assertJsonPath('summary.refund_count', 1)
            ->assertJsonPath('data.0.type', 'refund')->assertJsonPath('data.0.reason', 'Kopi tumpah')->assertJsonPath('data.0.number', $this->sale['number']);
    }

    public function test_export_and_access(): void
    {
        $this->actingAs($this->owner)->get("/api/v1/reports/products?outlet_id={$this->outlet->id}&export=xlsx")
            ->assertOk()->assertDownload('laporan-products-2026-10-15_2026-10-15.xlsx');
        $this->actingAs($this->cashier)->getJson('/api/v1/reports/profit')->assertForbidden();
        $this->actingAs($this->owner)->getJson('/api/v1/reports/unknown')->assertNotFound();
    }
}
