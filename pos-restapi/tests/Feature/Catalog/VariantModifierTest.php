<?php

namespace Tests\Feature\Catalog;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantModifierTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->owner = User::factory()->owner()->create(['pin' => '999999']);
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
    }

    /** Kopi Susu: Regular 15.000 (stok 10), Large 20.000 (stok 5) + Level Gula (wajib 1) + Topping (opsional, maks 2). */
    private function createKopi(): array
    {
        $gula = $this->actingAs($this->owner)->postJson('/api/v1/modifier-groups', [
            'name' => 'Level Gula', 'min_select' => 1, 'max_select' => 1,
            'modifiers' => [['name' => 'Normal'], ['name' => 'Less sugar']],
        ])->assertCreated()->json('data');

        $topping = $this->actingAs($this->owner)->postJson('/api/v1/modifier-groups', [
            'name' => 'Topping', 'min_select' => 0, 'max_select' => 2,
            'modifiers' => [['name' => 'Boba', 'price' => 3000], ['name' => 'Cheese foam', 'price' => 5000], ['name' => 'Jelly', 'price' => 2000]],
        ])->assertCreated()->json('data');

        $product = $this->actingAs($this->owner)->postJson('/api/v1/products', [
            'name' => 'Kopi Susu', 'sku' => 'KOPI', 'sell_price' => 15000,
            'variants' => [
                ['name' => 'Regular', 'sell_price' => 15000, 'cost_price' => 6000, 'initial_stock' => 10],
                ['name' => 'Large', 'sell_price' => 20000, 'cost_price' => 8000, 'barcode' => '8990000000020', 'initial_stock' => 5],
            ],
            'modifier_group_ids' => [$gula['id'], $topping['id']],
        ])->assertCreated()->json('data');

        return [$product, $gula, $topping];
    }

    private function stock(int $productId, ?int $variantId): int
    {
        return app(StockService::class)->quantity($this->outlet->id, $productId, $variantId);
    }

    public function test_create_product_with_variants_auto_sku_and_stock_per_variant(): void
    {
        [$product] = $this->createKopi();

        $this->assertSame(['KOPI-1', 'KOPI-2'], array_column($product['variants'], 'sku'));
        $this->assertTrue($product['has_variants']);
        $this->assertCount(2, $product['modifier_groups']);
        $this->assertTrue($product['modifier_groups'][0]['required']);
        $this->assertSame(10, $this->stock($product['id'], $product['variants'][0]['id']));
        $this->assertSame(5, $this->stock($product['id'], $product['variants'][1]['id']));
        $this->assertSame(0, $this->stock($product['id'], null));
    }

    public function test_update_syncs_variants(): void
    {
        [$product] = $this->createKopi();
        [$regular, $large] = $product['variants'];

        $updated = $this->actingAs($this->owner)->putJson("/api/v1/products/{$product['id']}", [
            'variants' => [
                ['id' => $large['id'], 'name' => 'Large', 'sell_price' => 22000],
                ['name' => 'Jumbo', 'sell_price' => 28000],
            ],
        ])->assertOk()->json('data.variants');

        $this->assertSame(['Large', 'Jumbo'], array_column($updated, 'name'));
        $this->assertSame(22000, $updated[0]['sell_price']);
        $this->assertSoftDeleted('product_variants', ['id' => $regular['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.price_changed']);
    }

    public function test_sale_with_variant_and_modifiers(): void
    {
        [$product, $gula, $topping] = $this->createKopi();
        $large = $product['variants'][1];

        $sale = $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [[
                'product_id' => $product['id'], 'variant_id' => $large['id'], 'quantity' => 2,
                'modifier_ids' => [$gula['modifiers'][1]['id'], $topping['modifiers'][0]['id'], $topping['modifiers'][1]['id']],
            ]],
            'payments' => [['method' => 'cash', 'amount' => 56000]],
        ])->assertCreated()->json('data');

        // (20.000 + 3.000 + 5.000) × 2
        $this->assertSame(28000, $sale['items'][0]['unit_price']);
        $this->assertSame(56000, $sale['grand_total']);
        $this->assertSame('Kopi Susu - Large', $sale['items'][0]['product_name']);
        $this->assertSame('KOPI-2', $sale['items'][0]['sku']);
        $this->assertSame(['Less sugar', 'Boba', 'Cheese foam'], array_column($sale['items'][0]['modifiers'], 'name'));
        $this->assertSame(3, $this->stock($product['id'], $large['id']));

        // Struk menampilkan modifier dengan harganya
        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$sale['id']}/receipt")
            ->assertJsonPath('data.items.0.modifiers', ['Less sugar', 'Boba (+3.000)', 'Cheese foam (+5.000)']);

        // Void mengembalikan stok varian
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale['id']}/void", ['reason' => 'Batal', 'supervisor_pin' => '999999'])->assertOk();
        $this->assertSame(5, $this->stock($product['id'], $large['id']));
    }

    public function test_variant_and_modifier_rules(): void
    {
        [$product, $gula, $topping] = $this->createKopi();
        $regular = $product['variants'][0];
        $sell = fn (array $item) => $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product['id'], 'quantity' => 1, ...$item]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
        ]);

        $sell(['modifier_ids' => [$gula['modifiers'][0]['id']]])
            ->assertStatus(422)->assertJsonPath('errors', ['items.0.variant_id' => ['Pilih varian Kopi Susu terlebih dahulu.']]);

        $sell(['variant_id' => $regular['id']])
            ->assertStatus(422)->assertJsonPath('errors', ['items.0.modifier_ids' => ['Pilih Level Gula untuk Kopi Susu (minimal 1).']]);

        $sell(['variant_id' => $regular['id'], 'modifier_ids' => [$gula['modifiers'][0]['id'], ...array_column($topping['modifiers'], 'id')]])
            ->assertStatus(422)->assertJsonPath('errors', ['items.0.modifier_ids' => ['Topping maksimal 2 pilihan.']]);

        $sell(['variant_id' => $regular['id'], 'modifier_ids' => [$gula['modifiers'][0]['id'], 99999]])
            ->assertStatus(422)->assertJsonPath('errors', ['items.0.modifier_ids' => ['Ada tambahan yang tidak tersedia untuk Kopi Susu. Pilih ulang.']]);
    }

    public function test_scan_variant_barcode_returns_product_with_matched_variant(): void
    {
        [$product] = $this->createKopi();

        $this->actingAs($this->cashier)->getJson('/api/v1/products/barcode/8990000000020')
            ->assertOk()
            ->assertJsonPath('data.id', $product['id'])
            ->assertJsonPath('data.matched_variant_id', $product['variants'][1]['id'])
            ->assertJsonMissingPath('data.variants.0.cost_price');
    }

    public function test_stock_list_shows_one_row_per_variant_and_adjustment_needs_variant(): void
    {
        [$product] = $this->createKopi();
        Product::factory()->create(['name' => 'Air Mineral', 'min_stock' => 0]);

        $rows = $this->actingAs($this->owner)->getJson("/api/v1/stocks?outlet_id={$this->outlet->id}")->assertOk()->json('data');
        $this->assertSame(['Air Mineral', 'Kopi Susu - Large', 'Kopi Susu - Regular'], array_column($rows, 'name'));
        $this->assertSame(['KOPI-2'], array_column(array_filter($rows, fn ($r) => $r['variant_name'] === 'Large'), 'sku'));

        $this->actingAs($this->owner)
            ->postJson('/api/v1/stock-adjustments', ['outlet_id' => $this->outlet->id, 'product_id' => $product['id'], 'quantity_change' => -1, 'reason' => 'damaged'])
            ->assertStatus(422)
            ->assertJsonPath('errors.variant_id.0', 'Pilih varian Kopi Susu yang akan disesuaikan.');

        $this->actingAs($this->owner)
            ->postJson('/api/v1/stock-adjustments', ['outlet_id' => $this->outlet->id, 'product_id' => $product['id'], 'variant_id' => $product['variants'][0]['id'], 'quantity_change' => -1, 'reason' => 'damaged'])
            ->assertCreated()
            ->assertJsonPath('data.quantity_after', 9);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/stocks/{$product['id']}/movements?outlet_id={$this->outlet->id}&variant_id={$product['variants'][0]['id']}")
            ->assertJsonPath('product.name', 'Kopi Susu - Regular')
            ->assertJsonPath('product.quantity', 9)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_modifier_group_validation(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/modifier-groups', [
            'name' => 'Topping', 'min_select' => 2, 'max_select' => 3, 'modifiers' => [['name' => 'Boba'], ['name' => 'boba']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_select', 'modifiers.0.name']);

        $this->actingAs($this->cashier)->postJson('/api/v1/modifier-groups', [])->assertStatus(403);
    }
}
