<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\PointMovement;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Customer $budi;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);
        Setting::put('loyalty.enabled', true);
        Setting::put('loyalty.earn_per', 10000);
        Setting::put('loyalty.point_value', 100);

        $outlet = Outlet::factory()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($outlet);
        User::factory()->manager()->create(['pin' => '999999'])->outlets()->attach($outlet);
        Shift::query()->create(['outlet_id' => $outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);

        $this->budi = Customer::factory()->create(['name' => 'Budi', 'points' => 0]);
        $this->product = Product::factory()->create(['sell_price' => 25000, 'track_stock' => false]);
    }

    private function sell(array $payments, int $qty = 1, ?int $customerId = null)
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->product->id, 'quantity' => $qty]],
            'payments' => $payments,
            'customer_id' => $customerId ?? $this->budi->id,
        ]);
    }

    public function test_earn_points_from_purchase(): void
    {
        // 50.000 / 10.000 = 5 poin
        $this->sell([['method' => 'cash', 'amount' => 50000]], 2)->assertCreated()->assertJsonPath('data.points_earned', 5);

        $this->assertSame(5, $this->budi->fresh()->points);
        $movement = PointMovement::query()->sole();
        $this->assertSame(['earn', 5, 5], [$movement->type, $movement->points, $movement->balance_after]);
    }

    public function test_redeem_points_as_payment_earns_only_on_non_point_part(): void
    {
        $this->budi->update(['points' => 100]); // = Rp10.000

        // 25.000: 10.000 poin + 15.000 tunai → poin didapat dari 15.000 = 1
        $this->sell([['method' => 'points', 'amount' => 10000], ['method' => 'cash', 'amount' => 15000]])
            ->assertCreated()
            ->assertJsonPath('data.points_redeemed', 100)
            ->assertJsonPath('data.points_earned', 1)
            ->assertJsonPath('data.payments.0.label', 'Poin');

        $this->assertSame(1, $this->budi->fresh()->points);
    }

    public function test_redeem_rules(): void
    {
        $this->budi->update(['points' => 50]);

        $this->sell([['method' => 'points', 'amount' => 10000], ['method' => 'cash', 'amount' => 15000]])
            ->assertStatus(422)
            ->assertJsonPath('errors.payments.0', 'Poin Budi tidak cukup. Sisa 50 poin (Rp5.000).');

        $this->sell([['method' => 'points', 'amount' => 150], ['method' => 'cash', 'amount' => 24850]])
            ->assertStatus(422)
            ->assertJsonPath('errors.payments.0', 'Nominal poin harus kelipatan Rp100 (nilai 1 poin).');

        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payments' => [['method' => 'points', 'amount' => 25000]],
        ])->assertStatus(422)->assertJsonPath('errors.payments.0', 'Pilih pelanggan terlebih dahulu untuk membayar dengan poin.');

        $this->assertSame(50, $this->budi->fresh()->points);
    }

    public function test_void_reverses_points(): void
    {
        $this->budi->update(['points' => 100]);
        $sale = $this->sell([['method' => 'points', 'amount' => 10000], ['method' => 'cash', 'amount' => 15000]])->json('data');

        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale['id']}/void", ['reason' => 'Batal beli', 'supervisor_pin' => '999999'])->assertOk();

        $this->assertSame(100, $this->budi->fresh()->points);
    }

    public function test_refund_claws_back_earned_points_and_can_refund_as_points(): void
    {
        $sale = $this->sell([['method' => 'cash', 'amount' => 100000]], 4)->json('data'); // 100.000 → 10 poin

        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale['id']}/refund", [
            'items' => [['sale_item_id' => $sale['items'][0]['id'], 'quantity' => 2]],
            'method' => 'points', 'reason' => 'Barang cacat', 'supervisor_pin' => '999999',
        ])->assertOk();

        // 10 − 5 (ditarik, 50%) + 500 (Rp50.000 dikembalikan sebagai poin)
        $this->assertSame(505, $this->budi->fresh()->points);
    }

    public function test_disabled_loyalty_earns_nothing_and_rejects_point_payment(): void
    {
        Setting::put('loyalty.enabled', false);
        $this->budi->update(['points' => 1000]);

        $this->sell([['method' => 'cash', 'amount' => 50000]], 2)->assertJsonPath('data.points_earned', 0);
        $this->sell([['method' => 'points', 'amount' => 25000]])->assertStatus(422)
            ->assertJsonPath('errors.payments.0', 'Pembayaran dengan poin sedang tidak aktif.');
    }
}
