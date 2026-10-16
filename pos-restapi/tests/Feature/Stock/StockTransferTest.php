<?php

namespace Tests\Feature\Stock;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $jkt;

    private Outlet $bdg;

    private User $jktStaff;

    private User $bdgStaff;

    private Product $kopi;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
        $this->jkt = Outlet::factory()->create(['code' => 'JKT01']);
        $this->bdg = Outlet::factory()->create(['code' => 'BDG01']);
        $this->jktStaff = User::factory()->create(['role' => Role::Warehouse]);
        $this->jktStaff->outlets()->attach($this->jkt);
        $this->bdgStaff = User::factory()->create(['role' => Role::Warehouse]);
        $this->bdgStaff->outlets()->attach($this->bdg);

        $this->kopi = Product::factory()->create(['name' => 'Kopi']);
        $this->gula = Product::factory()->create(['name' => 'Gula']);
        $stock = app(StockService::class);
        $stock->change($this->jkt->id, $this->kopi, 10, StockMovementType::Initial);
        $stock->change($this->jkt->id, $this->gula, 4, StockMovementType::Initial);
    }

    private function qty(Outlet $o, Product $p): int
    {
        return app(StockService::class)->quantity($o->id, $p->id);
    }

    private function send(array $items, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->jktStaff)->postJson('/api/v1/stock-transfers', [
            'from_outlet_id' => $this->jkt->id,
            'to_outlet_id' => $this->bdg->id,
            'items' => $items,
        ]);
    }

    public function test_send_reduces_source_and_receive_adds_to_destination(): void
    {
        $transfer = $this->send([['product_id' => $this->kopi->id, 'quantity' => 6], ['product_id' => $this->gula->id, 'quantity' => 2]])
            ->assertCreated()
            ->assertJsonPath('data.number', 'TRF-JKT01-20261015-0001')
            ->assertJsonPath('data.status', 'in_transit')
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.can_receive', false)
            ->json('data');
        $this->assertSame(4, $this->qty($this->jkt, $this->kopi));
        $this->assertSame(0, $this->qty($this->bdg, $this->kopi));

        // Outlet asal tidak bisa menerima
        $this->actingAs($this->jktStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", ['items' => []])->assertForbidden();

        $this->actingAs($this->bdgStaff)->getJson('/api/v1/stock-transfers?outlet_id='.$this->bdg->id.'&direction=in')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total_quantity', 8);
        $this->actingAs($this->bdgStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", ['items' => []])
            ->assertOk()->assertJsonPath('data.status', 'received')->assertJsonPath('data.received_by.id', $this->bdgStaff->id);

        $this->assertSame(6, $this->qty($this->bdg, $this->kopi));
        $this->assertSame(2, $this->qty($this->bdg, $this->gula));
        $this->assertSame(2, StockMovement::query()->where('type', 'transfer_out')->where('reference_type', 'stock_transfer')->count());
        $this->assertSame(2, StockMovement::query()->where('type', 'transfer_in')->where('outlet_id', $this->bdg->id)->count());

        $this->actingAs($this->bdgStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", ['items' => []])->assertConflict();
    }

    public function test_partial_receive_requires_note_and_rejects_more_than_sent(): void
    {
        $transfer = $this->send([['product_id' => $this->kopi->id, 'quantity' => 5]])->assertCreated()->json('data');
        $itemId = $transfer['items'][0]['id'];

        $errors = $this->actingAs($this->bdgStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", ['items' => [['id' => $itemId, 'quantity' => 6]]])
            ->assertUnprocessable()->json('errors');
        $this->assertSame(['Kopi: maksimal 5 sesuai jumlah dikirim.'], $errors["items.{$itemId}"]);

        $this->actingAs($this->bdgStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", ['items' => [['id' => $itemId, 'quantity' => 4]]])
            ->assertUnprocessable()->assertJsonPath('errors.note.0', 'Wajib diisi bila jumlah diterima kurang dari yang dikirim.');

        $this->actingAs($this->bdgStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", ['items' => [['id' => $itemId, 'quantity' => 4]], 'note' => '1 pecah di jalan'])
            ->assertOk()->assertJsonPath('data.items.0.received_quantity', 4)->assertJsonPath('data.receive_note', '1 pecah di jalan');
        $this->assertSame(4, $this->qty($this->bdg, $this->kopi));
        $this->assertSame(5, $this->qty($this->jkt, $this->kopi));
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_transfer.received']);
    }

    public function test_insufficient_stock_rejects_whole_transfer(): void
    {
        $this->send([['product_id' => $this->kopi->id, 'quantity' => 2], ['product_id' => $this->gula->id, 'quantity' => 9]])
            ->assertConflict()->assertJsonPath('items.0.name', 'Gula')->assertJsonPath('items.0.available', 4);
        $this->assertSame(10, $this->qty($this->jkt, $this->kopi));
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_cancel_returns_stock_to_source_and_validation(): void
    {
        $transfer = $this->send([['product_id' => $this->kopi->id, 'quantity' => 3]])->assertCreated()->json('data');
        $this->actingAs($this->bdgStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/cancel")->assertForbidden();
        $this->actingAs($this->jktStaff)->postJson("/api/v1/stock-transfers/{$transfer['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(10, $this->qty($this->jkt, $this->kopi));

        // Outlet asal harus milik user; tujuan harus berbeda
        $this->actingAs($this->bdgStaff)->postJson('/api/v1/stock-transfers', ['from_outlet_id' => $this->jkt->id, 'to_outlet_id' => $this->bdg->id, 'items' => [['product_id' => $this->kopi->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonPath('errors.from_outlet_id.0', 'Outlet asal tidak ditemukan atau bukan outlet Anda.');
        $this->actingAs($this->jktStaff)->postJson('/api/v1/stock-transfers', ['from_outlet_id' => $this->jkt->id, 'to_outlet_id' => $this->jkt->id, 'items' => [['product_id' => $this->kopi->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonPath('errors.to_outlet_id.0', 'Outlet tujuan harus berbeda dari outlet asal.');

        $other = User::factory()->create(['role' => Role::Warehouse]);
        $other->outlets()->attach(Outlet::factory()->create());
        $this->actingAs($other)->getJson("/api/v1/stock-transfers/{$transfer['id']}")->assertForbidden();
    }

    public function test_stock_staff_can_list_all_outlets_as_destination(): void
    {
        $this->actingAs($this->jktStaff)->getJson('/api/v1/outlets')->assertJsonCount(1, 'data');
        $this->actingAs($this->jktStaff)->getJson('/api/v1/outlets?all=1')->assertJsonCount(2, 'data');

        $cashier = User::factory()->cashier()->create();
        $cashier->outlets()->attach($this->jkt);
        $this->actingAs($cashier)->getJson('/api/v1/outlets?all=1')->assertJsonCount(1, 'data');
    }
}
