<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(8, 0));
        $this->outlet = Outlet::factory()->create();
        $this->cashier = User::factory()->cashier()->create(['name' => 'Rina', 'commission_percent' => 2.5]);
        $this->manager = User::factory()->manager()->create(['pin' => '999999']);
        $this->cashier->outlets()->attach($this->outlet);
        $this->manager->outlets()->attach($this->outlet);
    }

    public function test_clock_in_out_and_recap(): void
    {
        $this->actingAs($this->cashier)->getJson('/api/v1/attendances/current')->assertOk()->assertJsonPath('data', null);
        $this->actingAs($this->cashier)->postJson('/api/v1/attendances/clock-in', ['outlet_id' => $this->outlet->id])->assertCreated();
        $this->actingAs($this->cashier)->postJson('/api/v1/attendances/clock-in', ['outlet_id' => $this->outlet->id])->assertConflict();

        $this->travel(8)->hours();
        $this->actingAs($this->cashier)->postJson('/api/v1/attendances/clock-out', ['note' => 'Pulang tepat waktu'])
            ->assertOk()->assertJsonPath('data.minutes', 480);
        $this->actingAs($this->cashier)->postJson('/api/v1/attendances/clock-out')->assertConflict();

        $this->actingAs($this->manager)->getJson('/api/v1/attendances?from=2026-10-15&to=2026-10-15')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.0.user.name', 'Rina')->assertJsonPath('summary.0.minutes', 480)->assertJsonPath('summary.0.days', 1);
        $this->actingAs($this->cashier)->getJson('/api/v1/attendances')->assertForbidden();
    }

    public function test_opening_shift_clocks_in_automatically(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/v1/shifts/open', ['outlet_id' => $this->outlet->id, 'opening_cash' => 0])->assertCreated();
        $this->assertSame(1, Attendance::query()->open()->where('user_id', $this->cashier->id)->count());
    }

    public function test_commission_report_from_net_item_sales(): void
    {
        Setting::put('tax.enabled', true);
        $this->outlet->update(['tax_rate' => 10]);
        $this->actingAs($this->cashier)->postJson('/api/v1/shifts/open', ['outlet_id' => $this->outlet->id, 'opening_cash' => 0])->assertCreated();
        $kopi = Product::factory()->create(['sell_price' => 20000]);
        app(StockService::class)->change($this->outlet->id, $kopi, 10, StockMovementType::Initial);
        $sale = $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $kopi->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 110000]],
        ])->assertCreated()->json('data');
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale['id']}/refund", [
            'items' => [['sale_item_id' => $sale['items'][0]['id'], 'quantity' => 1]],
            'method' => 'cash', 'reason' => 'Salah pesan', 'supervisor_pin' => '999999',
        ])->assertOk();

        // Penjualan bersih item = 100.000 − 20.000 retur (tanpa pajak) → komisi 2,5% = 2.000
        $this->actingAs($this->manager)->getJson("/api/v1/reports/commissions?outlet_id={$this->outlet->id}")
            ->assertOk()->assertJsonPath('data.0.name', 'Rina')->assertJsonPath('data.0.net_sales', 80000)
            ->assertJsonPath('data.0.commission', 2000)->assertJsonPath('summary.commission', 2000);

        $this->actingAs($this->manager)->putJson("/api/v1/users/{$this->cashier->id}", ['commission_percent' => 3])
            ->assertOk()->assertJsonPath('data.commission_percent', 3);
    }
}
