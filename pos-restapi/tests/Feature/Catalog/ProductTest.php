<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Category $drinks;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->owner = User::factory()->owner()->create();
        $this->drinks = Category::factory()->create(['name' => 'Minuman']);
    }

    // ---- Tambah ----

    public function test_create_product_with_auto_sku(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', [
                'name' => 'Kopi Susu',
                'category_id' => $this->drinks->id,
                'sell_price' => 18000,
                'cost_price' => 9000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'MIN-0001')
            ->assertJsonPath('data.sell_price', 18000)
            ->assertJsonPath('data.cost_price', 9000)
            ->assertJsonPath('data.type', 'goods')
            ->assertJsonPath('data.unit', 'pcs')
            ->assertJsonPath('data.track_stock', true)
            ->assertJsonPath('data.category.name', 'Minuman');

        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'Teh', 'category_id' => $this->drinks->id, 'sell_price' => 8000])
            ->assertJsonPath('data.sku', 'MIN-0002');
    }

    public function test_auto_sku_without_category_and_after_deleted_product(): void
    {
        Product::factory()->create(['sku' => 'PRD-0007'])->delete();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'Kantong Plastik', 'sell_price' => 500])
            ->assertJsonPath('data.sku', 'PRD-0008');
    }

    public function test_manual_sku_and_uniqueness(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'A', 'sell_price' => 1000, 'sku' => 'KOPI-01'])
            ->assertJsonPath('data.sku', 'KOPI-01');

        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'B', 'sell_price' => 1000, 'sku' => 'KOPI-01'])
            ->assertStatus(422)
            ->assertJsonPath('errors.sku.0', 'SKU sudah dipakai produk lain. Kosongkan agar dibuat otomatis.');
    }

    public function test_barcode_unique_among_active_products_only(): void
    {
        Product::factory()->create(['barcode' => '8991234567890'])->delete();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'Baru', 'sell_price' => 1000, 'barcode' => '8991234567890'])
            ->assertCreated();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'Lagi', 'sell_price' => 1000, 'barcode' => '8991234567890'])
            ->assertStatus(422)
            ->assertJsonPath('errors.barcode.0', 'Barcode sudah dipakai produk lain.');
    }

    public function test_validation_messages_in_indonesian(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['sell_price' => -5, 'type' => 'composite'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Nama produk wajib diisi.')
            ->assertJsonPath('errors.sell_price.0', 'Harga jual minimal 0.')
            ->assertJsonPath('errors.type.0', 'Jenis produk yang dipilih tidak tersedia.');
    }

    public function test_service_product_never_tracks_stock(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', ['name' => 'Jasa Antar', 'sell_price' => 5000, 'type' => 'service', 'track_stock' => true])
            ->assertJsonPath('data.track_stock', false)
            ->assertJsonPath('data.type_label', 'Jasa');
    }

    public function test_create_with_image_via_multipart(): void
    {
        $response = $this->actingAs($this->owner)
            ->post('/api/v1/products', [
                'name' => 'Roti Bakar',
                'sell_price' => '18000',
                'track_stock' => 'true',
                'barcode' => '',
                'image' => UploadedFile::fake()->image('roti.jpg', 400, 400),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.barcode', null);

        $path = Product::query()->findOrFail($response->json('data.id'))->image_path;
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, $response->json('data.image_url'));
    }

    public function test_image_must_be_small_image(): void
    {
        $this->actingAs($this->owner)
            ->post('/api/v1/products', [
                'name' => 'X',
                'sell_price' => 1000,
                'image' => UploadedFile::fake()->create('besar.jpg', 3000, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'Ukuran gambar maksimal 2 MB.');
    }

    // ---- Daftar, detail, scan ----

    public function test_list_filters_and_search(): void
    {
        Product::factory()->create(['name' => 'Kopi Susu', 'category_id' => $this->drinks->id]);
        Product::factory()->create(['name' => 'Kopi Hitam', 'is_active' => false]);
        Product::factory()->create(['name' => 'Roti', 'barcode' => '111222333']);

        $this->actingAs($this->owner);
        $this->getJson('/api/v1/products?search=kopi')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/products?search=kopi&is_active=1')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/products?category_id={$this->drinks->id}")->assertJsonPath('data.0.name', 'Kopi Susu');
        $this->getJson('/api/v1/products?search=111222333')->assertJsonPath('data.0.name', 'Roti');
        $this->getJson('/api/v1/products?sort=-sell_price&per_page=200')->assertOk();
    }

    public function test_cashier_sees_products_without_cost_price(): void
    {
        $product = Product::factory()->create(['cost_price' => 9000]);

        $this->actingAs(User::factory()->cashier()->create())
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.cost_price')
            ->assertJsonPath('data.sell_price', $product->sell_price);
    }

    public function test_scan_by_barcode_or_sku(): void
    {
        Product::factory()->create(['name' => 'Kopi', 'barcode' => '8990001112223', 'sku' => 'MIN-0001']);

        $this->actingAs(User::factory()->cashier()->create());
        $this->getJson('/api/v1/products/barcode/8990001112223')->assertOk()->assertJsonPath('data.name', 'Kopi');
        $this->getJson('/api/v1/products/barcode/MIN-0001')->assertOk()->assertJsonPath('data.name', 'Kopi');
    }

    public function test_scan_unknown_or_inactive_returns_404_message(): void
    {
        Product::factory()->inactive()->create(['barcode' => '123']);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/products/barcode/123')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Produk dengan barcode 123 tidak ditemukan. Periksa barcode atau cari dengan nama produk.');
    }

    // ---- Ubah & hapus ----

    public function test_price_change_is_audited(): void
    {
        $product = Product::factory()->create(['sell_price' => 10000, 'cost_price' => 6000]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/products/{$product->id}", ['sell_price' => 12000])
            ->assertOk()
            ->assertJsonPath('data.sell_price', 12000);

        $log = AuditLog::query()->where('action', 'product.price_changed')->firstOrFail();
        // assertEquals: kolom JSON MySQL tidak menjaga urutan key
        $this->assertEquals(['sell_price' => 10000, 'cost_price' => 6000], $log->before_json);
        $this->assertEquals(['sell_price' => 12000, 'cost_price' => 6000], $log->after_json);
    }

    public function test_update_without_price_change_is_not_audited(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner)->putJson("/api/v1/products/{$product->id}", ['name' => 'Nama Baru'])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'product.price_changed']);
    }

    public function test_replace_and_remove_image(): void
    {
        $product = Product::factory()->create(['image_path' => UploadedFile::fake()->image('a.jpg')->store('products', 'public')]);
        $old = $product->image_path;

        // Multipart update: POST + _method=PUT
        $this->actingAs($this->owner)
            ->post("/api/v1/products/{$product->id}", ['_method' => 'PUT', 'image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])
            ->assertOk();
        $new = $product->fresh()->image_path;
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/products/{$product->id}", ['remove_image' => true])
            ->assertOk()
            ->assertJsonPath('data.image_url', null);
        Storage::disk('public')->assertMissing($new);
    }

    public function test_blank_sku_on_update_keeps_existing(): void
    {
        $product = Product::factory()->create(['sku' => 'ABC-1']);

        $this->actingAs($this->owner)->putJson("/api/v1/products/{$product->id}", ['sku' => ''])->assertJsonPath('data.sku', 'ABC-1');
    }

    public function test_delete_is_soft_and_audited(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner)->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.deleted', 'model_id' => $product->id]);
        $this->actingAs($this->owner)->getJson("/api/v1/products/{$product->id}")->assertStatus(404);
    }

    public function test_cashier_cannot_modify_products(): void
    {
        $product = Product::factory()->create();
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->postJson('/api/v1/products', ['name' => 'X', 'sell_price' => 1])->assertStatus(403);
        $this->actingAs($cashier)->putJson("/api/v1/products/{$product->id}", ['sell_price' => 1])->assertStatus(403);
        $this->actingAs($cashier)->deleteJson("/api/v1/products/{$product->id}")->assertStatus(403);
    }
}
