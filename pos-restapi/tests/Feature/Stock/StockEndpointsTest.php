<?php

namespace Tests\Feature\Stock;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $manager;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->manager = User::factory()->manager()->create();
        $this->manager->outlets()->attach($this->outlet);
        $this->stock = app(StockService::class);
    }

    private function product(string $name, int $qty, int $min = 5, array $attrs = []): Product
    {
        $product = Product::factory()->create(['name' => $name, 'min_stock' => $min, ...$attrs]);
        if ($qty > 0) {
            $this->stock->change($this->outlet->id, $product, $qty, StockMovementType::Initial);
        }

        return $product;
    }

    // ---- Daftar stok ----

    public function test_list_uses_default_outlet_and_marks_status(): void
    {
        $this->product('Aman', 20);
        $this->product('Menipis', 3);
        $this->product('Habis', 0);
        Product::factory()->service()->create(['name' => 'Jasa']);
        Product::factory()->create(['name' => 'Tidak dilacak', 'track_stock' => false]);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/stocks')->assertOk();

        $this->assertSame(['Aman' => 'ok', 'Habis' => 'out', 'Menipis' => 'low'], collect($response->json('data'))->pluck('status', 'name')->all());
        $response->assertJsonPath('summary', ['low' => 2, 'out' => 1, 'outlet_id' => $this->outlet->id])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.stock_value', 20 * Product::query()->where('name', 'Aman')->value('cost_price'));
    }

    public function test_list_filters_status_search_category_and_sort(): void
    {
        $drinks = Category::factory()->create();
        $this->product('Kopi', 20, 5, ['category_id' => $drinks->id]);
        $this->product('Teh', 2);
        $this->product('Roti', 0);

        $this->actingAs($this->manager);
        $this->getJson('/api/v1/stocks?status=low')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/stocks?status=out')->assertJsonPath('data.0.name', 'Roti');
        $this->getJson('/api/v1/stocks?search=kop')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/stocks?category_id={$drinks->id}")->assertJsonPath('data.0.name', 'Kopi');
        $this->getJson('/api/v1/stocks?sort=-quantity')->assertJsonPath('data.0.name', 'Kopi');
    }

    public function test_stock_is_isolated_per_outlet_and_access_checked(): void
    {
        $other = Outlet::factory()->create();
        $this->product('Kopi', 20);

        $this->actingAs($this->manager)
            ->getJson("/api/v1/stocks?outlet_id={$other->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.outlet_id.0', 'Outlet tidak ditemukan atau bukan outlet Anda.');

        $this->actingAs(User::factory()->owner()->create())
            ->getJson("/api/v1/stocks?outlet_id={$other->id}")
            ->assertOk()
            ->assertJsonPath('data.0.quantity', 0);
    }

    public function test_cashier_cannot_view_stock_but_warehouse_can(): void
    {
        $this->actingAs(User::factory()->cashier()->create())->getJson('/api/v1/stocks')->assertStatus(403);

        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $warehouse->outlets()->attach($this->outlet);
        $this->product('Kopi', 1);
        $this->actingAs($warehouse)
            ->getJson('/api/v1/stocks')
            ->assertOk()
            ->assertJsonMissingPath('data.0.cost_price');
    }

    // ---- Kartu stok ----

    public function test_movements_newest_first_with_current_quantity(): void
    {
        $this->actingAs($this->manager);
        $kopi = $this->product('Kopi', 10);
        $this->stock->change($this->outlet->id, $kopi, -4, StockMovementType::Sale);

        $this->getJson("/api/v1/stocks/{$kopi->id}/movements")
            ->assertOk()
            ->assertJsonPath('product.quantity', 6)
            ->assertJsonPath('data.0.type', 'sale')
            ->assertJsonPath('data.0.type_label', 'Penjualan')
            ->assertJsonPath('data.0.quantity_change', -4)
            ->assertJsonPath('data.0.quantity_after', 6)
            ->assertJsonPath('data.0.user.id', $this->manager->id)
            ->assertJsonPath('data.1.type', 'initial')
            ->assertJsonPath('meta.total', 2);

        $this->getJson("/api/v1/stocks/{$kopi->id}/movements?type=initial")->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/stocks/{$kopi->id}/movements?from=2000-01-01&to=2000-01-02")->assertJsonPath('meta.total', 0);
    }

    // ---- Penyesuaian ----

    public function test_adjustment_reduces_stock_with_reason_and_audit(): void
    {
        $kopi = $this->product('Kopi', 10);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $kopi->id,
                'quantity_change' => -2,
                'reason' => 'damaged',
                'note' => 'Kemasan sobek',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity_after', 8)
            ->assertJsonPath('data.note', 'Rusak: Kemasan sobek');

        $log = AuditLog::query()->where('action', 'stock.adjusted')->sole();
        $this->assertSame(10, $log->before_json['quantity']);
        $this->assertSame(8, $log->after_json['quantity']);
        $this->assertSame('product', $log->model_type);
    }

    public function test_adjustment_can_add_stock(): void
    {
        $kopi = $this->product('Kopi', 1);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/stock-adjustments', ['product_id' => $kopi->id, 'quantity_change' => 5, 'reason' => 'other', 'note' => 'Ditemukan di gudang'])
            ->assertCreated()
            ->assertJsonPath('data.quantity_after', 6);
    }

    public function test_adjustment_cannot_make_stock_negative(): void
    {
        $kopi = $this->product('Kopi', 2);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/stock-adjustments', ['product_id' => $kopi->id, 'quantity_change' => -5, 'reason' => 'lost'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Stok Kopi tidak cukup. Tersisa 2.');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'stock.adjusted']);
    }

    public function test_adjustment_validation(): void
    {
        $untracked = Product::factory()->create(['name' => 'Air Galon', 'track_stock' => false]);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/stock-adjustments', ['product_id' => $untracked->id, 'quantity_change' => 0, 'reason' => 'other'])
            ->assertStatus(422)
            ->assertJsonPath('errors.quantity_change.0', 'Jumlah tidak boleh 0. Isi angka positif untuk menambah atau negatif untuk mengurangi.')
            ->assertJsonPath('errors.note.0', 'Tulis keterangan untuk alasan "Lainnya".')
            ->assertJsonPath('errors.product_id.0', 'Air Galon tidak dilacak stoknya. Aktifkan "Lacak stok" di pengaturan produk terlebih dahulu.');
    }

    public function test_cashier_cannot_adjust(): void
    {
        $kopi = $this->product('Kopi', 2);
        $cashier = User::factory()->cashier()->create();
        $cashier->outlets()->attach($this->outlet);

        $this->actingAs($cashier)
            ->postJson('/api/v1/stock-adjustments', ['product_id' => $kopi->id, 'quantity_change' => -1, 'reason' => 'lost'])
            ->assertStatus(403);
    }
}
