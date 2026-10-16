<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Models\Voucher;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private User $manager;

    private Product $kopi;

    private Product $roti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0)); // Kamis
        Setting::put('tax.enabled', false);
        Setting::put('discount.manual_limit_percent', 10);

        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->cashier = User::factory()->cashier()->create();
        $this->manager = User::factory()->manager()->create(['pin' => '999999']);
        $this->cashier->outlets()->attach($this->outlet);
        $this->manager->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);

        $minuman = Category::query()->create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000, 'category_id' => $minuman->id]);
        $this->roti = Product::factory()->create(['name' => 'Roti', 'sell_price' => 20000, 'category_id' => null]);
        $stock = app(StockService::class);
        $stock->change($this->outlet->id, $this->kopi, 20, StockMovementType::Initial);
        $stock->change($this->outlet->id, $this->roti, 20, StockMovementType::Initial);
    }

    private function promo(array $attrs): Promotion
    {
        return Promotion::query()->create(['is_active' => true, 'value' => 0, ...$attrs]);
    }

    /** Kopi ×2 (30.000) + Roti ×1 (20.000) = 50.000 */
    private function sell(array $extra = [])
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2], ['product_id' => $this->roti->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
            ...$extra,
        ]);
    }

    private function quote(array $extra = [])
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales/quote', [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2], ['product_id' => $this->roti->id, 'quantity' => 1]],
            ...$extra,
        ]);
    }

    // ---- CRUD promo ----

    public function test_manager_creates_promotion_with_voucher_and_cashier_cannot(): void
    {
        $payload = ['name' => 'Diskon Akhir Pekan', 'type' => 'order_percent', 'value' => 10, 'rules' => ['min_purchase' => 50000],
            'requires_voucher' => true, 'voucher_code' => 'hemat10', 'voucher_quota' => 5];

        $this->actingAs($this->cashier)->postJson('/api/v1/promotions', $payload)->assertForbidden();

        $id = $this->actingAs($this->manager)->postJson('/api/v1/promotions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.voucher_code', 'HEMAT10')
            ->assertJsonPath('data.voucher_quota', 5)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.rules.min_purchase', 50000)
            ->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.created', 'model_id' => $id]);

        $this->actingAs($this->manager)->putJson("/api/v1/promotions/{$id}", [...$payload, 'value' => 15, 'voucher_code' => 'HEMAT10'])
            ->assertOk()->assertJsonPath('data.value', 15);
        $this->actingAs($this->manager)->getJson('/api/v1/promotions')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->manager)->deleteJson("/api/v1/promotions/{$id}")->assertNoContent();
        $this->assertSoftDeleted('promotions', ['id' => $id]);
    }

    public function test_promotion_validation_messages(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/promotions', ['name' => 'X', 'type' => 'item_percent', 'value' => 150])
            ->assertUnprocessable()->assertJsonPath('errors.value.0', 'Diskon persen maksimal 100.');
        $this->actingAs($this->manager)->postJson('/api/v1/promotions', ['name' => 'B2G1', 'type' => 'buy_x_get_y'])
            ->assertUnprocessable()->assertJsonValidationErrors(['rules.buy_product_id', 'rules.get_product_id']);
        $this->actingAs($this->manager)->postJson('/api/v1/promotions', ['name' => 'V', 'type' => 'order_amount', 'value' => 5000, 'requires_voucher' => true])
            ->assertUnprocessable()->assertJsonPath('errors.voucher_code.0', 'Isi kode voucher untuk promo yang hanya berlaku dengan voucher.');
    }

    // ---- Penerapan promo saat checkout ----

    public function test_item_promo_by_category_is_snapshotted_on_sale(): void
    {
        $this->promo(['name' => 'Kopi 20%', 'type' => 'item_percent', 'value' => 20, 'rules_json' => ['category_ids' => [$this->kopi->category_id]]]);

        $this->sell()->assertCreated()
            ->assertJsonPath('data.subtotal', 50000)
            ->assertJsonPath('data.discount_total', 6000)
            ->assertJsonPath('data.grand_total', 44000)
            ->assertJsonPath('data.items.0.discount', 6000)
            ->assertJsonPath('data.items.0.total', 24000)
            ->assertJsonPath('data.items.1.discount', 0)
            ->assertJsonPath('data.promotions.0.name', 'Kopi 20%')
            ->assertJsonPath('data.promotions.0.amount', 6000);
    }

    public function test_best_order_promo_wins_respecting_minimum_and_cap(): void
    {
        $this->promo(['name' => 'Potong 5rb', 'type' => 'order_amount', 'value' => 5000]);
        $this->promo(['name' => '20% maks 8rb', 'type' => 'order_percent', 'value' => 20, 'rules_json' => ['max_discount' => 8000]]);
        $this->promo(['name' => 'Min 100rb', 'type' => 'order_amount', 'value' => 30000, 'rules_json' => ['min_purchase' => 100000]]);

        $this->sell()->assertCreated()
            ->assertJsonPath('data.discount_total', 8000)
            ->assertJsonPath('data.grand_total', 42000)
            ->assertJsonCount(1, 'data.promotions')
            ->assertJsonPath('data.promotions.0.name', '20% maks 8rb');
        // Diskon transaksi dibagi proporsional ke baris: 30.000/50.000 × 8.000
        $this->assertSame([4800, 3200], Sale::query()->sole()->items->pluck('discount')->all());
    }

    public function test_buy_x_get_y_same_product(): void
    {
        $this->promo(['name' => 'Beli 2 Gratis 1 Kopi', 'type' => 'buy_x_get_y',
            'rules_json' => ['buy_product_id' => $this->kopi->id, 'buy_quantity' => 2, 'get_product_id' => $this->kopi->id, 'get_quantity' => 1]]);

        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 30000]],
        ])->assertCreated()->assertJsonPath('data.discount_total', 15000)->assertJsonPath('data.grand_total', 30000);
    }

    public function test_happy_hour_and_period_and_outlet_limits(): void
    {
        $this->promo(['name' => 'Happy hour', 'type' => 'order_amount', 'value' => 5000, 'rules_json' => ['days' => [4], 'start_time' => '15:00', 'end_time' => '17:00']]);
        $this->promo(['name' => 'Kadaluarsa', 'type' => 'order_amount', 'value' => 6000, 'ends_at' => now()->subDay()]);
        $this->promo(['name' => 'Outlet lain', 'type' => 'order_amount', 'value' => 7000, 'outlet_ids' => [$this->outlet->id + 100]]);
        $this->promo(['name' => 'Nonaktif', 'type' => 'order_amount', 'value' => 8000, 'is_active' => false]);

        $this->quote()->assertOk()->assertJsonPath('data.discount_total', 0);

        $this->travelTo(now()->setTime(15, 30));
        $this->quote()->assertOk()->assertJsonPath('data.discount_total', 5000)->assertJsonPath('data.promotions.0.name', 'Happy hour');
    }

    // ---- Voucher ----

    public function test_voucher_replaces_automatic_order_promo_and_uses_quota(): void
    {
        $this->promo(['name' => 'Otomatis', 'type' => 'order_amount', 'value' => 5000]);
        $promo = $this->promo(['name' => 'Voucher 3rb', 'type' => 'order_amount', 'value' => 3000, 'requires_voucher' => true]);
        $voucher = Voucher::query()->create(['code' => 'HEMAT', 'promotion_id' => $promo->id, 'quota' => 1]);

        $this->actingAs($this->cashier)->postJson('/api/v1/vouchers/validate', ['code' => 'hemat', 'outlet_id' => $this->outlet->id])
            ->assertOk()->assertJsonPath('data.promotion.name', 'Voucher 3rb');

        // Voucher dipakai → promo transaksi otomatis diganti
        $id = $this->sell(['voucher_code' => 'hemat'])->assertCreated()
            ->assertJsonPath('data.discount_total', 3000)
            ->assertJsonPath('data.promotions.0.name', 'Voucher 3rb')
            ->json('data.id');
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame($voucher->id, Sale::query()->find($id)->voucher_id);

        $this->sell(['voucher_code' => 'HEMAT'])->assertUnprocessable()->assertJsonPath('errors.voucher_code.0', 'Kuota voucher HEMAT sudah habis.');

        // Void mengembalikan kuota
        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$id}/void", ['reason' => 'Salah input', 'supervisor_pin' => '999999'])->assertOk();
        $this->assertSame(0, $voucher->fresh()->used_count);
    }

    public function test_unknown_or_out_of_period_voucher_is_rejected(): void
    {
        $promo = $this->promo(['name' => 'Lewat', 'type' => 'order_amount', 'value' => 3000, 'requires_voucher' => true, 'ends_at' => now()->subDay()]);
        Voucher::query()->create(['code' => 'LEWAT', 'promotion_id' => $promo->id]);

        $this->quote(['voucher_code' => 'TIDAKADA'])->assertUnprocessable()
            ->assertJsonPath('errors.voucher_code.0', 'Kode voucher TIDAKADA tidak ditemukan. Periksa kembali kodenya.');
        $this->sell(['voucher_code' => 'LEWAT'])->assertUnprocessable()
            ->assertJsonPath('errors.voucher_code.0', 'Voucher LEWAT tidak berlaku saat ini atau di outlet ini.');
        $this->assertSame(0, Sale::query()->count());
    }

    // ---- Diskon manual ----

    public function test_manual_discount_within_cashier_limit(): void
    {
        $this->quote(['manual_discount' => ['type' => 'percent', 'value' => 10]])->assertOk()
            ->assertJsonPath('data.manual_discount', 5000)->assertJsonPath('data.needs_supervisor', false);

        $this->sell(['manual_discount' => ['type' => 'amount', 'value' => 5000]])->assertCreated()
            ->assertJsonPath('data.manual_discount', 5000)->assertJsonPath('data.grand_total', 45000);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.manual_discount']);
    }

    public function test_manual_discount_over_limit_needs_supervisor_pin(): void
    {
        $this->quote(['manual_discount' => ['type' => 'percent', 'value' => 20]])->assertOk()->assertJsonPath('data.needs_supervisor', true);

        $this->sell(['manual_discount' => ['type' => 'percent', 'value' => 20]])->assertUnprocessable()->assertJsonValidationErrors('supervisor_pin');
        $this->sell(['manual_discount' => ['type' => 'percent', 'value' => 20], 'supervisor_pin' => '000000'])->assertUnprocessable();
        $this->assertSame(0, Sale::query()->count());

        $this->sell(['manual_discount' => ['type' => 'percent', 'value' => 20], 'supervisor_pin' => '999999'])->assertCreated()
            ->assertJsonPath('data.manual_discount', 10000)->assertJsonPath('data.grand_total', 40000);
        $log = AuditLog::query()->where('action', 'sale.manual_discount')->sole();
        $this->assertSame($this->manager->id, $log->after_json['approved_by']);

        // Manajer tidak dibatasi
        $this->actingAs($this->manager)->postJson('/api/v1/sales/quote', [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2]],
            'manual_discount' => ['type' => 'percent', 'value' => 50],
        ])->assertOk()->assertJsonPath('data.manual_discount', 15000)->assertJsonPath('data.needs_supervisor', false);
    }
}
