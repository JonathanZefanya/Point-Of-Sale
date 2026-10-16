<?php

namespace Tests\Feature\Stock;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $manager;

    private Product $kopi;

    private Product $roti;

    private Product $kaos;

    private ProductVariant $kaosM;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(20, 0));
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->manager = User::factory()->manager()->create();
        $this->manager->outlets()->attach($this->outlet);

        $minuman = Category::query()->create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'category_id' => $minuman->id]);
        $this->roti = Product::factory()->create(['name' => 'Roti']);
        Product::factory()->service()->create(['name' => 'Jasa Antar']); // tidak ikut opname
        $this->kaos = Product::factory()->create(['name' => 'Kaos']);
        $this->kaosM = $this->kaos->variants()->create(['name' => 'M', 'sku' => 'KAOS-M', 'sell_price' => 50000, 'cost_price' => 30000]);

        $stock = app(StockService::class);
        $stock->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
        $stock->change($this->outlet->id, $this->roti, 5, StockMovementType::Initial);
        $stock->change($this->outlet->id, $this->kaos, 3, StockMovementType::Initial, null, null, $this->kaosM->id);
    }

    private function start(array $payload = []): array
    {
        return $this->actingAs($this->manager)->postJson('/api/v1/stock-opnames', $payload)->assertCreated()->json('data');
    }

    private function qty(Product $p, ?int $variantId = null): int
    {
        return app(StockService::class)->quantity($this->outlet->id, $p->id, $variantId);
    }

    public function test_start_snapshots_tracked_products_and_variants(): void
    {
        $opname = $this->start();

        $this->assertSame('OPN-JKT01-20261015-0001', $opname['number']);
        $this->assertSame('draft', $opname['status']);
        $names = collect($opname['items'])->pluck('product_name')->all();
        $this->assertEqualsCanonicalizing(['Kopi', 'Roti', 'Kaos - M'], $names);
        $this->assertSame(10, collect($opname['items'])->firstWhere('product_name', 'Kopi')['system_quantity']);

        // Hanya satu draf per outlet
        $this->actingAs($this->manager)->postJson('/api/v1/stock-opnames')->assertConflict()->assertJsonPath('opname_id', $opname['id']);
    }

    public function test_start_by_category(): void
    {
        $opname = $this->start(['category_id' => $this->kopi->category_id]);
        $this->assertCount(1, $opname['items']);
        $this->assertSame('Minuman', $opname['category']['name']);
    }

    public function test_save_draft_then_apply_sets_stock_and_records_movements(): void
    {
        $opname = $this->start();
        $items = collect($opname['items'])->keyBy('product_name');

        // Simpan draf: stok belum berubah, selisih terhadap saldo terkini terlihat
        $this->actingAs($this->manager)->putJson("/api/v1/stock-opnames/{$opname['id']}", [
            'items' => [['id' => $items['Kopi']['id'], 'counted_quantity' => 8], ['id' => $items['Kaos - M']['id'], 'counted_quantity' => 5]],
            'note' => 'Hitung akhir bulan',
        ])->assertOk()->assertJsonPath('data.note', 'Hitung akhir bulan');
        $this->assertSame(10, $this->qty($this->kopi));

        // Penjualan terjadi selama menghitung → selisih dihitung dari saldo terkini
        app(StockService::class)->change($this->outlet->id, $this->kopi, -1, StockMovementType::Sale);
        $draft = $this->actingAs($this->manager)->getJson("/api/v1/stock-opnames/{$opname['id']}")->assertOk()->json('data.items');
        $this->assertSame(-1, collect($draft)->firstWhere('product_name', 'Kopi')['difference']);

        $applied = $this->actingAs($this->manager)->postJson("/api/v1/stock-opnames/{$opname['id']}/apply")
            ->assertOk()->assertJsonPath('data.status', 'applied')->assertJsonPath('data.applied_by.id', $this->manager->id)->json('data.items');

        $this->assertSame(8, $this->qty($this->kopi));
        $this->assertSame(5, $this->qty($this->kaos, $this->kaosM->id));
        $this->assertSame(5, $this->qty($this->roti)); // tidak dihitung → tidak berubah
        $this->assertSame(9, collect($applied)->firstWhere('product_name', 'Kopi')['system_quantity']);
        $this->assertSame(2, collect($applied)->firstWhere('product_name', 'Kaos - M')['difference']);
        $this->assertSame(2, StockMovement::query()->where('type', 'opname')->where('reference_type', 'stock_opname')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_opname.applied']);

        // Sudah diterapkan → tidak bisa diubah lagi; opname baru boleh dimulai
        $this->actingAs($this->manager)->postJson("/api/v1/stock-opnames/{$opname['id']}/apply")->assertConflict();
        $this->start();
    }

    public function test_apply_requires_at_least_one_count_and_cancel_draft(): void
    {
        $opname = $this->start();
        $this->actingAs($this->manager)->postJson("/api/v1/stock-opnames/{$opname['id']}/apply")
            ->assertUnprocessable()->assertJsonPath('message', 'Belum ada jumlah fisik yang diisi. Isi minimal satu produk sebelum menerapkan opname.');

        $this->actingAs($this->manager)->postJson("/api/v1/stock-opnames/{$opname['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($this->manager)->getJson('/api/v1/stock-opnames?status=cancelled')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_access_rules(): void
    {
        $opname = $this->start();
        $cashier = User::factory()->cashier()->create();
        $cashier->outlets()->attach($this->outlet);
        $this->actingAs($cashier)->postJson('/api/v1/stock-opnames')->assertForbidden();
        $this->actingAs($cashier)->getJson("/api/v1/stock-opnames/{$opname['id']}")->assertForbidden();

        $other = User::factory()->create(['role' => Role::Warehouse]);
        $other->outlets()->attach(Outlet::factory()->create());
        $this->actingAs($other)->putJson("/api/v1/stock-opnames/{$opname['id']}", ['items' => []])->assertForbidden();
    }
}
