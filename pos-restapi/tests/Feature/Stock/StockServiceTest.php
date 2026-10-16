<?php

namespace Tests\Feature\Stock;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockChange;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
        $this->outlet = Outlet::factory()->create();
    }

    public function test_change_writes_movement_and_updates_balance(): void
    {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['cost_price' => 7000]);

        $in = $this->stock->change($this->outlet->id, $product, 10, StockMovementType::Purchase, null, 'Barang datang');
        $out = $this->stock->change($this->outlet->id, $product, -3, StockMovementType::Sale);

        $this->assertSame(7, $this->stock->quantity($this->outlet->id, $product->id));
        $this->assertSame([10, 10], [$in->quantity_change, $in->quantity_after]);
        $this->assertSame([-3, 7], [$out->quantity_change, $out->quantity_after]);
        $this->assertSame(7000, $in->cost_price);
        $this->assertSame($user->id, $in->user_id);
        $this->assertSame('Barang datang', $in->note);
        $this->assertSame(StockMovementType::Sale, $out->type);
    }

    public function test_stock_cannot_go_negative(): void
    {
        $product = Product::factory()->create(['name' => 'Kopi Susu']);
        $this->stock->change($this->outlet->id, $product, 2, StockMovementType::Initial);

        try {
            $this->stock->change($this->outlet->id, $product, -3, StockMovementType::Sale);
            $this->fail('Seharusnya stok kurang');
        } catch (InsufficientStockException $e) {
            $this->assertSame('Stok Kopi Susu tidak cukup. Tersisa 2.', $e->getMessage());
            $this->assertSame(409, $e->status);
            $this->assertSame([['product_id' => $product->id, 'variant_id' => null, 'name' => 'Kopi Susu', 'available' => 2, 'requested' => 3]], $e->items);
        }

        $this->assertSame(2, $this->stock->quantity($this->outlet->id, $product->id));
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_multi_item_is_all_or_nothing_and_lists_every_shortage(): void
    {
        $kopi = Product::factory()->create(['name' => 'Kopi']);
        $teh = Product::factory()->create(['name' => 'Teh']);
        $roti = Product::factory()->create(['name' => 'Roti']);
        $this->stock->apply($this->outlet->id, [new StockChange($kopi->id, 5), new StockChange($teh->id, 1), new StockChange($roti->id, 1)], StockMovementType::Initial);

        try {
            $this->stock->apply($this->outlet->id, [
                new StockChange($kopi->id, -2),  // cukup
                new StockChange($teh->id, -2),   // kurang
                new StockChange($roti->id, -4),  // kurang
            ], StockMovementType::Sale);
            $this->fail('Seharusnya stok kurang');
        } catch (InsufficientStockException $e) {
            $this->assertSame('Stok beberapa produk tidak cukup: Teh (sisa 1), Roti (sisa 1).', $e->getMessage());
            $this->assertCount(2, $e->items);
        }

        // Kopi juga tidak berkurang
        $this->assertSame(5, $this->stock->quantity($this->outlet->id, $kopi->id));
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Sale)->count());
    }

    public function test_same_product_in_multiple_lines_is_summed(): void
    {
        $product = Product::factory()->create();
        $this->stock->change($this->outlet->id, $product, 5, StockMovementType::Initial);

        $movements = $this->stock->apply($this->outlet->id, [new StockChange($product->id, -2), new StockChange($product->id, -2)], StockMovementType::Sale);

        $this->assertCount(1, $movements);
        $this->assertSame(-4, $movements[0]->quantity_change);
        $this->assertSame(1, $this->stock->quantity($this->outlet->id, $product->id));

        $this->expectException(InsufficientStockException::class);
        $this->stock->apply($this->outlet->id, [new StockChange($product->id, -1), new StockChange($product->id, -1)], StockMovementType::Sale);
    }

    public function test_untracked_and_service_products_are_skipped(): void
    {
        $untracked = Product::factory()->create(['track_stock' => false]);
        $service = Product::factory()->service()->create();

        $movements = $this->stock->apply($this->outlet->id, [new StockChange($untracked->id, -99), new StockChange($service->id, -1)], StockMovementType::Sale);

        $this->assertSame([], $movements);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_is_per_outlet(): void
    {
        $other = Outlet::factory()->create();
        $product = Product::factory()->create();

        $this->stock->change($this->outlet->id, $product, 8, StockMovementType::Initial);
        $this->stock->change($other->id, $product, 3, StockMovementType::Initial);

        $this->assertSame(8, $this->stock->quantity($this->outlet->id, $product->id));
        $this->assertSame(3, $this->stock->quantity($other->id, $product->id));
        $this->expectException(InsufficientStockException::class);
        $this->stock->change($other->id, $product, -4, StockMovementType::Sale);
    }

    public function test_deleted_product_can_still_receive_stock_back(): void
    {
        $product = Product::factory()->create();
        $this->stock->change($this->outlet->id, $product, 2, StockMovementType::Initial);
        $product->delete();

        $this->stock->change($this->outlet->id, $product, 1, StockMovementType::SaleVoid);

        $this->assertSame(3, $this->stock->quantity($this->outlet->id, $product->id));
    }

    public function test_set_quantity_records_difference(): void
    {
        $product = Product::factory()->create();
        $this->stock->change($this->outlet->id, $product, 10, StockMovementType::Initial);

        $movement = $this->stock->setQuantity($this->outlet->id, $product, 7, StockMovementType::Opname, null, 'Hitung fisik');
        $this->assertSame([-3, 7], [$movement->quantity_change, $movement->quantity_after]);

        $this->assertNull($this->stock->setQuantity($this->outlet->id, $product, 7, StockMovementType::Opname));
    }

    public function test_reference_is_stored(): void
    {
        $product = Product::factory()->create();

        $movement = $this->stock->change($this->outlet->id, $product, 1, StockMovementType::Initial, $product);

        $this->assertTrue($movement->reference->is($product));
    }
}
