<?php

namespace Tests\Feature\Customer;

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $owner;

    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);
        $this->outlet = Outlet::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($this->outlet);
        $this->owner = User::factory()->owner()->create();
    }

    public function test_cashier_creates_customer_with_normalized_phone(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/customers', ['name' => '  Budi Santoso ', 'phone' => '+62 812-3456-7890'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Budi Santoso')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.group_label', 'Member')
            ->assertJsonPath('data.visits', 0);

        $this->actingAs($this->cashier)
            ->postJson('/api/v1/customers', ['name' => 'Lain', 'phone' => '0812 3456 7890'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Nomor HP ini sudah terdaftar atas pelanggan lain. Cari pelanggan dengan nomor ini.');

        $this->actingAs($this->cashier)
            ->postJson('/api/v1/customers', ['name' => 'X', 'phone' => '123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Nomor HP tidak valid. Contoh: 081234567890.');
    }

    public function test_search_by_name_or_phone(): void
    {
        Customer::factory()->create(['name' => 'Budi', 'phone' => '081111111111']);
        Customer::factory()->create(['name' => 'Siti', 'phone' => '082222222222']);

        $this->actingAs($this->cashier)->getJson('/api/v1/customers?search=bud')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->cashier)->getJson('/api/v1/customers?search=%2B62822222')->assertJsonPath('data.0.name', 'Siti');
    }

    public function test_only_owner_or_manager_updates_and_deletes(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->cashier)->putJson("/api/v1/customers/{$customer->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAs($this->cashier)->deleteJson("/api/v1/customers/{$customer->id}")->assertStatus(403);

        $this->actingAs($this->owner)->putJson("/api/v1/customers/{$customer->id}", ['group' => 'vip'])->assertOk()->assertJsonPath('data.group_label', 'VIP');
        $this->actingAs($this->owner)->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();
        $this->assertSoftDeleted($customer);
    }

    public function test_sale_with_customer_and_history(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi']);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $product = Product::factory()->create(['sell_price' => 10000]);
        app(StockService::class)->change($this->outlet->id, $product, 5, StockMovementType::Initial);

        $sale = $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20000]],
            'customer_id' => $customer->id,
        ])->assertCreated()->assertJsonPath('data.customer.name', 'Budi')->json('data');

        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$sale['id']}/receipt")->assertJsonPath('data.customer', 'Budi');
        $this->actingAs($this->cashier)->getJson("/api/v1/customers/{$customer->id}/sales")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.number', $sale['number']);
        $this->actingAs($this->cashier)->getJson("/api/v1/customers/{$customer->id}")
            ->assertJsonPath('data.visits', 1)->assertJsonPath('data.total_spent', 20000);
    }

    public function test_deleted_customer_cannot_be_used_in_sale(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $product = Product::factory()->create(['track_stock' => false]);

        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 999999]],
            'customer_id' => $customer->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
    }
}
