<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrisPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test';

    private Outlet $outlet;

    private User $cashier;

    private Product $kopi;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::KEY, 'services.midtrans.is_production' => false]);
        Setting::put('tax.enabled', false);
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
        $this->kopi = Product::factory()->create(['sell_price' => 15000]);
        app(StockService::class)->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
    }

    private function fakeCharge(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201', 'transaction_id' => 'trx-1', 'qr_string' => '00020101...',
                'actions' => [['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/trx-1/qr-code']],
            ]),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '15000.00']),
        ]);
    }

    private function notify(PaymentIntent $intent, string $status, string $amount = '15000.00', ?string $signature = null)
    {
        return $this->postJson('/api/v1/payments/midtrans/notification', [
            'order_id' => $intent->order_id, 'status_code' => '200', 'gross_amount' => $amount, 'transaction_status' => $status,
            'signature_key' => $signature ?? hash('sha512', $intent->order_id.'200'.$amount.self::KEY),
        ]);
    }

    private function checkout(int $intentId, int $amount = 15000)
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'outlet_id' => $this->outlet->id,
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1]],
            'payments' => [['method' => 'qris', 'amount' => $amount, 'intent_id' => $intentId]],
        ]);
    }

    public function test_full_flow_create_qr_webhook_paid_and_checkout(): void
    {
        $this->fakeCharge();
        $this->actingAs($this->cashier)->getJson('/api/v1/payments/gateway')->assertJsonPath('data.qris_dynamic', true);

        $data = $this->actingAs($this->cashier)->postJson('/api/v1/payments/qris', ['outlet_id' => $this->outlet->id, 'amount' => 15000])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');
        $this->assertStringStartsWith('POS-JKT01-', $data['order_id']);
        $this->assertSame('https://api.sandbox.midtrans.com/v2/qris/trx-1/qr-code', $data['qr_url']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/charge') && $r['payment_type'] === 'qris' && $r['transaction_details']['gross_amount'] === 15000);

        // Belum lunas → checkout ditolak
        $this->checkout($data['id'])->assertUnprocessable()->assertJsonPath('errors.payments.0', 'Pembayaran QRIS belum lunas. Tunggu pelanggan menyelesaikan pembayaran.');

        $intent = PaymentIntent::query()->findOrFail($data['id']);
        $this->notify($intent, 'settlement', signature: 'salah')->assertForbidden();
        $this->notify($intent, 'settlement')->assertOk();
        $this->actingAs($this->cashier)->getJson("/api/v1/payments/qris/{$data['id']}")->assertJsonPath('data.status', 'paid');

        $saleId = $this->checkout($data['id'])->assertCreated()->json('data.id');
        $this->assertSame($saleId, $intent->fresh()->sale_id);

        // QR yang sama tidak bisa dipakai transaksi lain
        $this->checkout($data['id'])->assertUnprocessable()->assertJsonPath('errors.payments.0', 'Pembayaran QRIS ini sudah dipakai di transaksi lain.');
    }

    public function test_amount_mismatch_and_status_never_downgraded(): void
    {
        $this->fakeCharge();
        $id = $this->actingAs($this->cashier)->postJson('/api/v1/payments/qris', ['outlet_id' => $this->outlet->id, 'amount' => 15000])->json('data.id');
        $intent = PaymentIntent::query()->findOrFail($id);

        $this->notify($intent, 'settlement', '1000.00')->assertOk();
        $this->assertSame('failed', $intent->fresh()->status);

        $intent->update(['status' => 'pending']);
        $this->notify($intent, 'settlement')->assertOk();
        $this->notify($intent, 'expire')->assertOk();
        $this->assertSame('paid', $intent->fresh()->status);

        $this->checkout($id, 20000)->assertUnprocessable();
    }

    public function test_polling_asks_midtrans_status_and_disabled_gateway(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response(['status_code' => '201', 'transaction_id' => 't', 'actions' => []]),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '15000.00']),
        ]);
        $id = $this->actingAs($this->cashier)->postJson('/api/v1/payments/qris', ['outlet_id' => $this->outlet->id, 'amount' => 15000])->json('data.id');
        $this->actingAs($this->cashier)->getJson("/api/v1/payments/qris/{$id}")->assertJsonPath('data.status', 'paid');

        config(['services.midtrans.server_key' => null]);
        $this->actingAs($this->cashier)->getJson('/api/v1/payments/gateway')->assertJsonPath('data.qris_dynamic', false);
        $this->actingAs($this->cashier)->postJson('/api/v1/payments/qris', ['outlet_id' => $this->outlet->id, 'amount' => 15000])->assertConflict();
    }
}
