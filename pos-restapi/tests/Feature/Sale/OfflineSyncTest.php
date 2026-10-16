<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private Shift $shift;

    private Product $kopi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        Setting::put('tax.enabled', false);
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->cashier = User::factory()->cashier()->create();
        $this->cashier->outlets()->attach($this->outlet);
        $this->shift = Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now()->subHours(3), 'opening_cash' => 0]);
        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000, 'cost_price' => 7000]);
        app(StockService::class)->change($this->outlet->id, $this->kopi, 3, StockMovementType::Initial);
    }

    private function offlineSale(array $override = []): array
    {
        return [
            'client_id' => 'c-'.uniqid(),
            'outlet_id' => $this->outlet->id,
            'shift_id' => $this->shift->id,
            'completed_at' => now()->subHour()->toIso8601String(),
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 2, 'unit_price' => 15000]],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
            'grand_total' => 30000,
            ...$override,
        ];
    }

    private function sync(array $sales)
    {
        return $this->actingAs($this->cashier)->postJson('/api/v1/sales/sync', ['sales' => $sales]);
    }

    public function test_offline_sale_is_recorded_with_offline_time_and_stock(): void
    {
        $sale = $this->offlineSale();
        $result = $this->sync([$sale])->assertOk()->json('data.0');

        $this->assertSame('created', $result['status']);
        $this->assertSame([], $result['warnings']);
        $stored = Sale::query()->sole();
        $this->assertSame('JKT01-20261015-0001', $stored->number);
        $this->assertSame('11:00', $stored->completed_at->format('H:i'));
        $this->assertNotNull($stored->synced_at);
        $this->assertSame(20000, $stored->change_amount);
        $this->assertSame(1, app(StockService::class)->quantity($this->outlet->id, $this->kopi->id));

        // Kirim ulang (koneksi putus sebelum respons diterima) → tidak dobel
        $this->sync([$sale])->assertOk()->assertJsonPath('data.0.status', 'duplicate')->assertJsonPath('data.0.sale.number', 'JKT01-20261015-0001');
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_device_price_is_kept_and_differences_are_flagged(): void
    {
        $this->kopi->update(['sell_price' => 17000]); // harga naik setelah kasir offline

        $result = $this->sync([$this->offlineSale()])->assertOk()->json('data.0');

        $this->assertSame('created', $result['status']);
        $this->assertSame(['Harga Kopi di perangkat Rp15.000, di server Rp17.000.'], $result['warnings']);
        $this->assertSame(30000, Sale::query()->sole()->grand_total);
        $log = AuditLog::query()->where('action', 'sale.offline_conflict')->sole();
        $this->assertSame($result['warnings'], $log->after_json['warnings']);
    }

    public function test_stock_shortage_is_accepted_and_stock_goes_negative(): void
    {
        $result = $this->sync([$this->offlineSale([
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 5, 'unit_price' => 15000]],
            'payments' => [['method' => 'cash', 'amount' => 75000]],
            'grand_total' => 75000,
        ])])->assertOk()->json('data.0');

        $this->assertSame('created', $result['status']);
        $this->assertSame(['Stok Kopi tidak cukup saat sinkron (tersisa 3, terjual 5); stok menjadi minus.'], $result['warnings']);
        $this->assertSame(-2, app(StockService::class)->quantity($this->outlet->id, $this->kopi->id));
    }

    public function test_batch_processes_each_sale_independently(): void
    {
        $this->shift->update(['closed_at' => now()]);
        $this->kopi->delete();

        $results = $this->sync([
            $this->offlineSale(),
            $this->offlineSale(['payments' => [['method' => 'points', 'amount' => 30000]]]),
            $this->offlineSale(['shift_id' => 999]),
        ])->assertOk()->json('data');

        $this->assertSame('created', $results[0]['status']);
        $this->assertContains('Kopi sudah tidak dijual saat transaksi disinkronkan.', $results[0]['warnings']);
        $this->assertContains('Shift sudah ditutup sebelum transaksi ini tersinkron; laporan kas shift tidak memuat transaksi ini.', $results[0]['warnings']);
        $this->assertSame('failed', $results[1]['status']);
        $this->assertSame('failed', $results[2]['status']);
        $this->assertSame('Shift transaksi ini bukan milik kasir ini di outlet tersebut.', $results[2]['message']);
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_sale_resource_shows_sync_warnings(): void
    {
        $this->kopi->update(['sell_price' => 16000]);
        $id = $this->sync([$this->offlineSale()])->json('data.0.sale.id');

        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$id}")
            ->assertOk()->assertJsonCount(1, 'data.sync_warnings')->assertJsonPath('data.grand_total', 30000);
    }
}
