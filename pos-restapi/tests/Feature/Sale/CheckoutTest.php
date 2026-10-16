<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private Shift $shift;

    private Product $kopi;

    private Product $roti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        Setting::put('tax.enabled', true);
        Setting::put('tax.inclusive', false);

        $this->outlet = Outlet::factory()->create(['code' => 'JKT01', 'tax_rate' => 10]);
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($this->outlet);
        $this->shift = Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 100000]);

        $this->kopi = Product::factory()->create(['name' => 'Kopi Susu', 'sku' => 'MIN-0001', 'sell_price' => 15000, 'cost_price' => 7000]);
        $this->roti = Product::factory()->create(['name' => 'Roti Bakar', 'sku' => 'MKN-0001', 'sell_price' => 18000, 'cost_price' => 9000]);
        $stock = app(StockService::class);
        $stock->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
        $stock->change($this->outlet->id, $this->roti, 5, StockMovementType::Initial);
    }

    private function cart(array $payments, array $override = []): array
    {
        return [
            'items' => [
                ['product_id' => $this->kopi->id, 'quantity' => 2],
                ['product_id' => $this->roti->id, 'quantity' => 1, 'note' => 'Tanpa keju'],
            ],
            'payments' => $payments,
            ...$override,
        ];
    }

    public function test_normal_cash_sale_with_change(): void
    {
        $response = $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 100000]]))
            ->assertCreated()
            ->assertJsonPath('data.number', 'JKT01-20261015-0001')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.subtotal', 48000)
            ->assertJsonPath('data.tax_total', 4800)
            ->assertJsonPath('data.grand_total', 52800)
            ->assertJsonPath('data.paid_total', 100000)
            ->assertJsonPath('data.change_amount', 47200)
            ->assertJsonPath('data.items.1.note', 'Tanpa keju')
            ->assertJsonPath('data.payments.0.label', 'Tunai')
            ->assertJsonMissingPath('data.items.0.cost_price'); // kasir tidak melihat harga modal

        // Stok berkurang lewat StockService, movement merujuk ke transaksi
        $stock = app(StockService::class);
        $this->assertSame(8, $stock->quantity($this->outlet->id, $this->kopi->id));
        $this->assertSame(4, $stock->quantity($this->outlet->id, $this->roti->id));
        $movement = StockMovement::query()->where('type', 'sale')->where('product_id', $this->kopi->id)->sole();
        $this->assertSame('sale', $movement->reference_type);
        $this->assertSame($response->json('data.id'), $movement->reference_id);
        $this->assertSame('JKT01-20261015-0001', $movement->note);
    }

    public function test_snapshot_is_kept_when_product_changes_later(): void
    {
        $id = $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart([['method' => 'qris', 'amount' => 52800]]))->json('data.id');

        $this->kopi->update(['name' => 'Kopi Baru', 'sell_price' => 99000]);

        $item = Sale::query()->findOrFail($id)->items()->where('product_id', $this->kopi->id)->sole();
        $this->assertSame(['Kopi Susu', 'MIN-0001', 15000, 7000], [$item->product_name, $item->sku, $item->unit_price, $item->cost_price]);
    }

    public function test_split_payment(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', $this->cart([
                ['method' => 'qris', 'amount' => 30000, 'reference' => 'QR123'],
                ['method' => 'cash', 'amount' => 25000],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.paid_total', 55000)
            ->assertJsonPath('data.change_amount', 2200)
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.payments.0.reference', 'QR123');
    }

    public function test_insufficient_stock_returns_409_and_keeps_everything_unchanged(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $this->kopi->id, 'quantity' => 2], ['product_id' => $this->roti->id, 'quantity' => 6]],
                'payments' => [['method' => 'cash', 'amount' => 200000]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Stok Roti Bakar tidak cukup. Tersisa 5.')
            ->assertJsonPath('items.0.product_id', $this->roti->id)
            ->assertJsonPath('items.0.available', 5);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(10, app(StockService::class)->quantity($this->outlet->id, $this->kopi->id));

        // Nomor urut tidak "terpakai" oleh transaksi gagal
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 52800]]))
            ->assertJsonPath('data.number', 'JKT01-20261015-0001');
    }

    public function test_double_submit_with_same_idempotency_key_creates_one_sale(): void
    {
        $payload = $this->cart([['method' => 'cash', 'amount' => 52800]]);

        $first = $this->actingAs($this->cashier)->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/v1/sales', $payload)->assertCreated();
        $second = $this->actingAs($this->cashier)->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/v1/sales', $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(8, app(StockService::class)->quantity($this->outlet->id, $this->kopi->id));
    }

    public function test_number_sequence_resets_per_day_and_per_outlet(): void
    {
        $pay = [['method' => 'cash', 'amount' => 52800]];
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart($pay))->assertJsonPath('data.number', 'JKT01-20261015-0001');
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart($pay))->assertJsonPath('data.number', 'JKT01-20261015-0002');

        $this->travel(1)->day();
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart($pay))->assertJsonPath('data.number', 'JKT01-20261016-0001');
    }

    public function test_requires_open_shift(): void
    {
        $this->shift->update(['closed_at' => now()]);

        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 52800]]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Buka shift terlebih dahulu sebelum mencatat transaksi.');
    }

    public function test_payment_rules(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 50000]]))
            ->assertStatus(422)
            ->assertJsonPath('errors.payments.0', 'Pembayaran kurang Rp2.800. Total transaksi Rp52.800.')
            ->assertJsonPath('grand_total', 52800);

        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', $this->cart([['method' => 'debit', 'amount' => 60000]]))
            ->assertStatus(422)
            ->assertJsonPath('errors.payments.0', fn ($m) => str_starts_with($m, 'Pembayaran non-tunai melebihi total.'));

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_inactive_or_deleted_product_is_rejected_per_line(): void
    {
        $this->roti->update(['is_active' => false]);

        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 100000]]))
            ->assertStatus(422)
            ->assertJsonPath('errors', ['items.1.product_id' => ['Roti Bakar tidak lagi dijual. Hapus dari keranjang.']]);
    }

    public function test_client_cannot_set_prices(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $this->kopi->id, 'quantity' => 1, 'unit_price' => 1]],
                'payments' => [['method' => 'cash', 'amount' => 16500]],
                'grand_total' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 15000)
            ->assertJsonPath('data.grand_total', 16500);
    }

    public function test_empty_cart_and_warehouse_rejected(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales', ['items' => [], 'payments' => []])
            ->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Keranjang masih kosong. Tambahkan produk terlebih dahulu.');

        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $warehouse->outlets()->attach($this->outlet);
        $this->actingAs($warehouse)->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 52800]]))->assertStatus(403);
    }

    public function test_shift_close_includes_net_cash_sales(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart([['method' => 'cash', 'amount' => 100000]]));
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', $this->cart([['method' => 'qris', 'amount' => 52800]]));

        // Saat shift masih terbuka, tunai tidak ditampilkan di laporan
        $this->actingAs($this->cashier)->getJson("/api/v1/shifts/{$this->shift->id}")
            ->assertJsonPath('report.transaction_count', 2)
            ->assertJsonPath('report.payments', [['method' => 'qris', 'label' => 'QRIS', 'amount' => 52800]]);

        // Kas seharusnya = 100.000 modal + 52.800 (100.000 dibayar − 47.200 kembalian)
        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$this->shift->id}/close", ['actual_cash' => 152800])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', 152800)
            ->assertJsonPath('report.sales_total', 105600)
            ->assertJsonPath('report.payments.0.method', 'cash')
            ->assertJsonPath('report.payments.0.amount', 52800);
    }
}
