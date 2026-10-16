<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $manager;

    private User $cashier;

    private Product $kopi;

    private DiningTable $meja1;

    private DiningTable $meja2;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);
        $this->outlet = Outlet::factory()->create();
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->manager->outlets()->attach($this->outlet);
        $this->cashier->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000]);
        app(StockService::class)->change($this->outlet->id, $this->kopi, 20, StockMovementType::Initial);
        $this->meja1 = DiningTable::query()->create(['outlet_id' => $this->outlet->id, 'name' => 'Meja 1']);
        $this->meja2 = DiningTable::query()->create(['outlet_id' => $this->outlet->id, 'name' => 'Meja 2']);
    }

    private function hold(DiningTable $table, int $qty = 1, array $extra = [])
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales/hold', [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->kopi->id, 'quantity' => $qty]],
            'table_id' => $table->id,
            ...$extra,
        ]);
    }

    public function test_manage_tables(): void
    {
        $id = $this->actingAs($this->manager)->postJson('/api/v1/tables', ['outlet_id' => $this->outlet->id, 'name' => 'Teras 1', 'area' => 'Teras', 'capacity' => 6])
            ->assertCreated()->assertJsonPath('data.sort_order', 1)->json('data.id'); // setelah urutan tertinggi (0)
        $this->actingAs($this->manager)->postJson('/api/v1/tables', ['outlet_id' => $this->outlet->id, 'name' => 'Meja 1'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Nama meja ini sudah ada di outlet ini. Pakai nama lain, mis. "Meja 12".');
        $this->actingAs($this->manager)->putJson("/api/v1/tables/{$id}", ['name' => 'Teras A'])->assertOk()->assertJsonPath('data.name', 'Teras A');
        $this->actingAs($this->cashier)->postJson('/api/v1/tables', ['name' => 'X'])->assertForbidden();

        $this->hold($this->meja1)->assertCreated();
        $this->actingAs($this->manager)->deleteJson("/api/v1/tables/{$this->meja1->id}")->assertConflict();
        $this->actingAs($this->manager)->deleteJson("/api/v1/tables/{$id}")->assertNoContent();
    }

    public function test_table_bill_occupies_table_until_paid(): void
    {
        $sale = $this->hold($this->meja1, 2)->assertCreated()
            ->assertJsonPath('data.table.name', 'Meja 1')->assertJsonPath('data.order_type', 'dine_in')->json('data');

        $tables = collect($this->actingAs($this->cashier)->getJson("/api/v1/tables?outlet_id={$this->outlet->id}")->assertOk()->json('data'))->keyBy('name');
        $this->assertTrue($tables['Meja 1']['occupied']);
        $this->assertSame(30000, $tables['Meja 1']['open_sale']['grand_total']);
        $this->assertFalse($tables['Meja 2']['occupied']);

        // Meja terisi tidak bisa dipakai bill lain
        $this->hold($this->meja1)->assertConflict()->assertJsonPath('table_sale_id', $sale['id']);

        // Bayar bill → meja kosong lagi, tipe pesanan & nama meja tersimpan di transaksi
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $this->outlet->id, 'held_sale_id' => $sale['id'],
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 30000]],
        ])->assertCreated()->assertJsonPath('data.table.name', 'Meja 1')->assertJsonPath('data.order_type_label', 'Makan di tempat');
        $this->actingAs($this->cashier)->getJson("/api/v1/tables?outlet_id={$this->outlet->id}")->assertJsonPath('data.0.occupied', false);
        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$sale['id']}/receipt")->assertJsonPath('data.table', 'Meja 1');
    }

    public function test_move_and_merge_bills(): void
    {
        $a = $this->hold($this->meja1, 1)->json('data.id');
        $b = $this->hold($this->meja2, 2)->json('data.id');

        $this->actingAs($this->cashier)->postJson("/api/v1/sales/held/{$a}/move", ['table_id' => $this->meja2->id])->assertConflict();
        $meja3 = DiningTable::query()->create(['outlet_id' => $this->outlet->id, 'name' => 'Meja 3']);
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/held/{$a}/move", ['table_id' => $meja3->id])
            ->assertOk()->assertJsonPath('data.table.name', 'Meja 3');

        // Gabung bill Meja 3 ke Meja 2: item digabung, total dihitung ulang, bill asal hilang
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/held/{$a}/merge", ['target_sale_id' => $b])
            ->assertOk()->assertJsonPath('data.id', $b)->assertJsonPath('data.table.name', 'Meja 2')
            ->assertJsonCount(2, 'data.items')->assertJsonPath('data.grand_total', 45000);
        $this->assertNull(Sale::query()->find($a));

        $this->actingAs($this->cashier)->postJson("/api/v1/sales/held/{$b}/merge", ['target_sale_id' => $b])->assertUnprocessable();
    }

    public function test_take_away_order_type_without_table(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $this->outlet->id, 'order_type' => 'take_away',
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 15000]],
        ])->assertCreated()->assertJsonPath('data.order_type', 'take_away')->assertJsonPath('data.table', null);
    }
}
