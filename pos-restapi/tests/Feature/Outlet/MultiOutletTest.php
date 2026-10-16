<?php

namespace Tests\Feature\Outlet;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiOutletTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Outlet $jkt;

    private Outlet $bdg;

    private Product $kopi;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);
        $this->owner = User::factory()->owner()->create();
        $this->jkt = Outlet::factory()->create(['code' => 'JKT01', 'name' => 'Jakarta']);
        $this->bdg = Outlet::factory()->create(['code' => 'BDG01', 'name' => 'Bandung']);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000]);
    }

    // ---- CRUD outlet ----

    public function test_owner_manages_outlets_and_others_cannot(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/v1/outlets', ['code' => 'sby01', 'name' => 'Surabaya', 'tax_rate' => 11])
            ->assertCreated()->assertJsonPath('data.code', 'SBY01')->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'outlet.created', 'model_id' => $id]);

        $this->actingAs($this->owner)->postJson('/api/v1/outlets', ['code' => 'JKT01', 'name' => 'Dobel'])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'Kode outlet ini sudah dipakai outlet lain (termasuk yang sudah dihapus).');
        $this->actingAs($this->owner)->postJson('/api/v1/outlets', ['code' => 'JKT 2', 'name' => 'X'])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'Kode outlet hanya huruf besar dan angka tanpa spasi, mis. JKT02.');

        $this->actingAs($this->owner)->putJson("/api/v1/outlets/{$id}", ['code' => 'SBY01', 'name' => 'Surabaya Kota'])->assertOk()->assertJsonPath('data.name', 'Surabaya Kota');
        $this->actingAs($this->owner)->getJson("/api/v1/outlets/{$id}")->assertOk();

        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach($this->jkt);
        $this->actingAs($manager)->postJson('/api/v1/outlets', ['code' => 'X1', 'name' => 'X'])->assertForbidden();
        $this->actingAs($manager)->deleteJson("/api/v1/outlets/{$id}")->assertForbidden();
        $this->actingAs($manager)->getJson("/api/v1/outlets/{$id}")->assertForbidden();

        $this->actingAs($this->owner)->deleteJson("/api/v1/outlets/{$id}")->assertNoContent();
        $this->assertSoftDeleted('outlets', ['id' => $id]);
    }

    public function test_cannot_delete_outlet_with_open_shift_or_last_outlet(): void
    {
        $cashier = User::factory()->cashier()->create();
        Shift::query()->create(['outlet_id' => $this->bdg->id, 'user_id' => $cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $this->actingAs($this->owner)->deleteJson("/api/v1/outlets/{$this->bdg->id}")
            ->assertConflict()->assertJsonPath('message', 'Masih ada shift terbuka di Bandung. Tutup semua shift sebelum menghapus outlet.');

        $this->actingAs($this->owner)->deleteJson("/api/v1/outlets/{$this->jkt->id}")->assertNoContent();
        Shift::query()->where('outlet_id', $this->bdg->id)->update(['closed_at' => now()]);
        $this->actingAs($this->owner)->deleteJson("/api/v1/outlets/{$this->bdg->id}")
            ->assertConflict()->assertJsonPath('message', 'Outlet terakhir tidak bisa dihapus. Tambahkan outlet lain terlebih dahulu.');
    }

    // ---- Harga & ketersediaan per outlet ----

    private function setOutletPrices(array $rows): void
    {
        $this->actingAs($this->owner)->putJson("/api/v1/products/{$this->kopi->id}", ['outlet_prices' => $rows])->assertOk();
    }

    public function test_outlet_price_and_availability_in_catalog_and_sale(): void
    {
        $this->setOutletPrices([
            ['outlet_id' => $this->jkt->id, 'sell_price' => 18000, 'is_available' => true],
            ['outlet_id' => $this->bdg->id, 'sell_price' => null, 'is_available' => false],
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.outlet_prices_changed']);

        // Back office tanpa konteks outlet: harga default + daftar harga outlet
        $this->actingAs($this->owner)->getJson("/api/v1/products/{$this->kopi->id}")
            ->assertJsonPath('data.sell_price', 15000)->assertJsonCount(2, 'data.outlet_prices');

        // Katalog kasir per outlet
        $this->actingAs($this->owner)->getJson("/api/v1/products?outlet_id={$this->jkt->id}&available_only=1")
            ->assertJsonPath('data.0.sell_price', 18000)->assertJsonPath('data.0.is_available', true);
        $this->actingAs($this->owner)->getJson("/api/v1/products?outlet_id={$this->bdg->id}&available_only=1")->assertJsonCount(0, 'data');
        $this->actingAs($this->owner)->getJson("/api/v1/products/barcode/{$this->kopi->sku}?outlet_id={$this->bdg->id}")
            ->assertNotFound()->assertJsonPath('message', 'Kopi tidak dijual di outlet ini.');

        // Penjualan: harga outlet dipakai, produk yang tidak dijual ditolak
        $cashier = User::factory()->cashier()->create();
        $cashier->outlets()->attach([$this->jkt->id, $this->bdg->id]);
        foreach ([$this->jkt, $this->bdg] as $outlet) {
            Shift::query()->create(['outlet_id' => $outlet->id, 'user_id' => $cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
            app(StockService::class)->change($outlet->id, $this->kopi, 5, StockMovementType::Initial);
        }
        $sale = fn (Outlet $o) => $this->actingAs($cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $o->id,
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ]);
        $sale($this->jkt)->assertCreated()->assertJsonPath('data.subtotal', 36000);
        $errors = $sale($this->bdg)->assertUnprocessable()->json('errors');
        $this->assertSame(['Kopi tidak dijual di outlet ini. Hapus dari keranjang.'], $errors['items.0.product_id']);
    }

    public function test_default_rows_are_removed_and_cashier_cannot_see_other_outlet_prices(): void
    {
        $this->setOutletPrices([['outlet_id' => $this->jkt->id, 'sell_price' => 18000, 'is_available' => true]]);
        $this->setOutletPrices([['outlet_id' => $this->jkt->id, 'sell_price' => null, 'is_available' => true]]);
        $this->assertDatabaseCount('product_outlet_prices', 0);

        $this->setOutletPrices([['outlet_id' => $this->jkt->id, 'sell_price' => 18000, 'is_available' => true]]);
        $cashier = User::factory()->cashier()->create();
        $cashier->outlets()->attach($this->bdg);
        // Outlet yang tidak boleh diakses diabaikan → harga default; harga outlet tidak terlihat oleh kasir
        $this->actingAs($cashier)->getJson("/api/v1/products?outlet_id={$this->jkt->id}")
            ->assertJsonPath('data.0.sell_price', 15000)->assertJsonMissingPath('data.0.outlet_prices');
    }
}
