<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private User $manager;

    private Shift $shift;

    private Product $kopi;

    private Product $roti;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', true);
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01', 'tax_rate' => 10]);
        $this->cashier = User::factory()->cashier()->create();
        $this->manager = User::factory()->manager()->create(['pin' => '999999']);
        $this->cashier->outlets()->attach($this->outlet);
        $this->manager->outlets()->attach($this->outlet);
        $this->shift = Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 100000]);

        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000, 'cost_price' => 6000]);
        $this->roti = Product::factory()->create(['name' => 'Roti', 'sell_price' => 18000, 'cost_price' => 9000]);
        app(StockService::class)->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
        app(StockService::class)->change($this->outlet->id, $this->roti, 10, StockMovementType::Initial);
    }

    /** 2 kopi + 1 roti = 48.000 + pajak 4.800 = 52.800, tunai 60.000 */
    private function sell(): array
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2], ['product_id' => $this->roti->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 60000]],
        ])->assertCreated()->json('data');
    }

    private function refund(array $sale, array $items, array $extra = [])
    {
        return $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale['id']}/refund", [
            'items' => $items, 'method' => 'cash', 'reason' => 'Barang cacat', 'supervisor_pin' => '999999', ...$extra,
        ]);
    }

    private function stock(Product $p): int
    {
        return app(StockService::class)->quantity($this->outlet->id, $p->id);
    }

    public function test_partial_refund_proportional_amount_restock_and_audit(): void
    {
        $sale = $this->sell();
        $kopiLine = $sale['items'][0]['id'];

        // 1 kopi = 15.000 dari subtotal 48.000 → 52.800 × 15.000/48.000 = 16.500 (termasuk pajak)
        $this->refund($sale, [['sale_item_id' => $kopiLine, 'quantity' => 1]])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial_refund')
            ->assertJsonPath('data.refunded_total', 16500)
            ->assertJsonPath('data.items.0.refunded_quantity', 1)
            ->assertJsonPath('data.refunds.0.approved_by.id', $this->manager->id);

        $this->assertSame(9, $this->stock($this->kopi));
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.refunded']);
        $this->assertDatabaseHas('stock_movements', ['type' => 'refund', 'reference_type' => 'refund', 'quantity_change' => 1]);
    }

    public function test_full_refund_in_steps_totals_exactly_grand_total(): void
    {
        $sale = $this->sell();
        [$kopi, $roti] = [$sale['items'][0]['id'], $sale['items'][1]['id']];

        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1]])->assertOk();
        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1], ['sale_item_id' => $roti, 'quantity' => 1, 'restock' => false]])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refunded_total', 52800);

        $this->assertSame(10, $this->stock($this->kopi));
        $this->assertSame(9, $this->stock($this->roti)); // rusak: tidak kembali ke stok

        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1]])
            ->assertStatus(409)
            ->assertJsonPath('message', "Transaksi {$sale['number']} berstatus Dikembalikan dan tidak bisa di-refund.");
    }

    public function test_cannot_refund_more_than_remaining(): void
    {
        $sale = $this->sell();
        $kopi = $sale['items'][0]['id'];
        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1]])->assertOk();

        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 2]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['items.0.quantity' => ['Kopi: maksimal 1 yang masih bisa dikembalikan.']]);
    }

    public function test_cash_refund_reduces_expected_cash_and_report_revenue(): void
    {
        $sale = $this->sell();
        $this->refund($sale, [['sale_item_id' => $sale['items'][0]['id'], 'quantity' => 1]])->assertOk();

        // Kas: 100.000 + 52.800 − 16.500 = 136.300
        $this->actingAs($this->cashier)->postJson("/api/v1/shifts/{$this->shift->id}/close", ['actual_cash' => 136300])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', 136300)
            ->assertJsonPath('report.refund_total', 16500);

        $this->actingAs($this->manager)->getJson('/api/v1/reports/sales')
            ->assertJsonPath('summary.refunds', 16500)
            ->assertJsonPath('summary.net', 52800 - 16500)
            ->assertJsonPath('summary.transactions', 1);
        $this->actingAs($this->manager)->getJson('/api/v1/reports/payments')
            ->assertJsonPath('data.0.amount', 52800 - 16500);
        // Laba: (48.000 − 21.000) − (15.000 − 6.000)
        $this->actingAs($this->manager)->getJson('/api/v1/reports/dashboard')
            ->assertJsonPath('data.current.gross_profit', 27000 - 9000);
    }

    public function test_refund_requires_pin_open_shift_and_blocks_void(): void
    {
        $sale = $this->sell();
        $kopi = $sale['items'][0]['id'];

        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1]], ['supervisor_pin' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('errors.supervisor_pin.0', 'PIN supervisor salah atau bukan milik pemilik/manajer outlet ini.');

        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1]])->assertOk();
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale['id']}/void", ['reason' => 'Batal saja', 'supervisor_pin' => '999999'])->assertStatus(409);

        $this->shift->update(['closed_at' => now()]);
        $this->refund($sale, [['sale_item_id' => $kopi, 'quantity' => 1]])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Buka shift di outlet ini terlebih dahulu'));

        $this->assertSame(1, AuditLog::query()->where('action', 'sale.refunded')->count());
    }
}
