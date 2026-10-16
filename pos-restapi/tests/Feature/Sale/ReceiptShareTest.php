<?php

namespace Tests\Feature\Sale;

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

class ReceiptShareTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private array $sale;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'http://pos.test']);
        Setting::put('tax.enabled', false);
        Setting::put('loyalty.enabled', false);
        $outlet = Outlet::factory()->create(['code' => 'JKT01', 'name' => 'Kopi Senja']);
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($outlet);
        Shift::query()->create(['outlet_id' => $outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $kopi = Product::factory()->create(['sell_price' => 15000]);
        app(StockService::class)->change($outlet->id, $kopi, 5, StockMovementType::Initial);
        $customer = Customer::factory()->create(['phone' => '0812-3456-7890']);

        $this->sale = $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $outlet->id, 'customer_id' => $customer->id,
            'items' => [['product_id' => $kopi->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 30000]],
        ])->assertCreated()->json('data');
    }

    public function test_share_builds_whatsapp_message_and_signed_link(): void
    {
        $data = $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$this->sale['id']}/share")->assertOk()->json('data');

        $this->assertSame('6281234567890', $data['phone']);
        $this->assertStringStartsWith("http://pos.test/struk/{$this->sale['id']}?expires=", $data['url']);
        $this->assertStringContainsString('Terima kasih telah berbelanja di Kopi Senja.', $data['text']);
        $this->assertStringContainsString('Total: Rp30.000', $data['text']);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $data['whatsapp_url']);

        // Halaman web meneruskan query yang sama ke API publik (tanpa login)
        $query = parse_url($data['url'], PHP_URL_QUERY);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/public/receipts/{$this->sale['id']}?{$query}")
            ->assertOk()->assertJsonPath('data.number', $this->sale['number'])->assertJsonPath('data.grand_total', 30000);
    }

    public function test_public_receipt_rejects_tampered_or_missing_signature(): void
    {
        $data = $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$this->sale['id']}/share")->json('data');
        parse_str(parse_url($data['url'], PHP_URL_QUERY), $q);
        $this->app['auth']->forgetGuards();

        $other = $this->sale['id'] + 1;
        $this->getJson("/api/v1/public/receipts/{$other}?expires={$q['expires']}&signature={$q['signature']}")->assertForbidden();
        $this->getJson("/api/v1/public/receipts/{$this->sale['id']}")->assertForbidden();

        $this->travel(31)->days();
        $this->getJson("/api/v1/public/receipts/{$this->sale['id']}?expires={$q['expires']}&signature={$q['signature']}")->assertForbidden();
    }
}
