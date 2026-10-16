<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private Product $kopi;

    private Product $roti;

    private DiningTable $meja;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);
        Setting::put('kitchen.enabled', true);
        $this->outlet = Outlet::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000]);
        $this->roti = Product::factory()->create(['name' => 'Roti', 'sell_price' => 10000]);
        foreach ([$this->kopi, $this->roti] as $p) {
            app(StockService::class)->change($this->outlet->id, $p, 50, StockMovementType::Initial);
        }
        $this->meja = DiningTable::query()->create(['outlet_id' => $this->outlet->id, 'name' => 'Meja 7']);
    }

    private function hold(array $items, ?int $heldId = null)
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales/hold', [
            'outlet_id' => $this->outlet->id, 'table_id' => $this->meja->id, 'held_sale_id' => $heldId, 'items' => $items,
        ])->assertCreated()->json('data.id');
    }

    public function test_only_new_items_are_sent_as_additional_tickets(): void
    {
        $id = $this->hold([['product_id' => $this->kopi->id, 'quantity' => 2, 'note' => 'Tanpa gula']]);
        $this->hold([['product_id' => $this->kopi->id, 'quantity' => 2, 'note' => 'Tanpa gula'], ['product_id' => $this->roti->id, 'quantity' => 1]], $id);
        // Simpan ulang tanpa perubahan → tidak ada tiket baru
        $this->hold([['product_id' => $this->kopi->id, 'quantity' => 2, 'note' => 'Tanpa gula'], ['product_id' => $this->roti->id, 'quantity' => 1]], $id);
        // Bayar dengan tambahan 1 kopi → tiket hanya berisi 1 kopi
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $this->outlet->id, 'held_sale_id' => $id,
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 3, 'note' => 'Tanpa gula'], ['product_id' => $this->roti->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 55000]],
        ])->assertCreated();

        $tickets = KitchenTicket::query()->orderBy('id')->get();
        $this->assertCount(3, $tickets);
        $this->assertSame('Meja 7', $tickets[0]->label);
        $this->assertEquals([['name' => 'Kopi', 'quantity' => 2, 'modifiers' => [], 'note' => 'Tanpa gula']], $tickets[0]->items); // MySQL JSON mengurutkan ulang key
        $this->assertSame('Roti', $tickets[1]->items[0]['name']);
        $this->assertTrue($tickets[1]->is_addition);
        $this->assertSame(1, $tickets[2]->items[0]['quantity']);
    }

    public function test_kitchen_screen_lists_and_updates_tickets(): void
    {
        $this->hold([['product_id' => $this->kopi->id, 'quantity' => 1]]);
        $ticket = $this->actingAs($this->cashier)->getJson("/api/v1/kitchen/tickets?outlet_id={$this->outlet->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'new')
            ->assertJsonPath('data.0.order_type_label', 'Makan di tempat')->json('data.0');

        $this->actingAs($this->cashier)->postJson("/api/v1/kitchen/tickets/{$ticket['id']}/status", ['status' => 'ready'])
            ->assertOk()->assertJsonPath('data.status_label', 'Siap');
        $this->actingAs($this->cashier)->postJson("/api/v1/kitchen/tickets/{$ticket['id']}/status", ['status' => 'served'])->assertOk();
        $this->actingAs($this->cashier)->getJson("/api/v1/kitchen/tickets?outlet_id={$this->outlet->id}")->assertJsonCount(0, 'data');

        $other = User::factory()->cashier('654321')->create();
        $other->outlets()->attach(Outlet::factory()->create());
        $this->actingAs($other)->postJson("/api/v1/kitchen/tickets/{$ticket['id']}/status", ['status' => 'new'])->assertForbidden();
    }

    public function test_disabled_kitchen_creates_no_tickets(): void
    {
        Setting::put('kitchen.enabled', false);
        $this->hold([['product_id' => $this->kopi->id, 'quantity' => 1]]);
        $this->assertSame(0, KitchenTicket::query()->count());
    }

    public function test_merge_does_not_resend_items(): void
    {
        $a = $this->hold([['product_id' => $this->kopi->id, 'quantity' => 1]]);
        $meja2 = DiningTable::query()->create(['outlet_id' => $this->outlet->id, 'name' => 'Meja 8']);
        $b = $this->actingAs($this->cashier)->postJson('/api/v1/sales/hold', [
            'outlet_id' => $this->outlet->id, 'table_id' => $meja2->id, 'items' => [['product_id' => $this->roti->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->actingAs($this->cashier)->postJson("/api/v1/sales/held/{$a}/merge", ['target_sale_id' => $b])->assertOk();
        $this->assertSame(2, KitchenTicket::query()->count());
    }
}
