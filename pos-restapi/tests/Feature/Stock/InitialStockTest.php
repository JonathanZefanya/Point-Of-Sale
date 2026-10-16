<?php

namespace Tests\Feature\Stock;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_stock_uses_only_outlet_automatically(): void
    {
        $outlet = Outlet::factory()->create();
        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach($outlet);

        $id = $this->actingAs($manager)
            ->postJson('/api/v1/products', ['name' => 'Kopi', 'sell_price' => 18000, 'cost_price' => 9000, 'initial_stock' => 24])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(24, app(StockService::class)->quantity($outlet->id, $id));
        $movement = StockMovement::query()->sole();
        $this->assertSame(StockMovementType::Initial, $movement->type);
        $this->assertSame('Stok awal', $movement->note);
        $this->assertSame(9000, $movement->cost_price);
        $this->assertSame($manager->id, $movement->user_id);
    }

    public function test_owner_with_many_outlets_must_choose_outlet(): void
    {
        Outlet::factory()->count(2)->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/products', ['name' => 'Kopi', 'sell_price' => 18000, 'initial_stock' => 5])
            ->assertStatus(422)
            ->assertJsonPath('errors.outlet_id.0', 'Pilih outlet tempat stok awal disimpan.');
    }

    public function test_outlet_must_be_accessible(): void
    {
        $mine = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach($mine);

        $this->actingAs($manager)
            ->postJson('/api/v1/products', ['name' => 'Kopi', 'sell_price' => 1000, 'initial_stock' => 5, 'outlet_id' => $other->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.outlet_id.0', 'Outlet untuk stok awal tidak ditemukan atau bukan outlet Anda.');
    }

    public function test_no_movement_for_zero_initial_stock_or_service(): void
    {
        $outlet = Outlet::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->postJson('/api/v1/products', ['name' => 'A', 'sell_price' => 1000, 'initial_stock' => 0])->assertCreated();
        $this->actingAs($owner)
            ->postJson('/api/v1/products', ['name' => 'Jasa', 'sell_price' => 1000, 'type' => 'service', 'initial_stock' => 5, 'outlet_id' => $outlet->id])
            ->assertCreated();

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_initial_stock_is_ignored_on_update(): void
    {
        $outlet = Outlet::factory()->create();
        $owner = User::factory()->owner()->create();
        $id = $this->actingAs($owner)->postJson('/api/v1/products', ['name' => 'A', 'sell_price' => 1000])->json('data.id');

        $this->actingAs($owner)->putJson("/api/v1/products/{$id}", ['initial_stock' => 50, 'outlet_id' => $outlet->id])->assertOk();

        $this->assertSame(0, StockMovement::query()->count());
    }
}
