<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Mail\DailyReportMail;
use App\Mail\LowStockMail;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->owner()->create();
        $outlet = Outlet::factory()->create(['code' => 'JKT01', 'name' => 'Kopi Senja']);
        $kopi = Product::factory()->create(['name' => 'Kopi', 'min_stock' => 5]);
        Product::factory()->create(['name' => 'Gula', 'min_stock' => 5]);
        app(StockService::class)->change($outlet->id, $kopi, 3, StockMovementType::Initial);
    }

    public function test_settings_validate_recipients(): void
    {
        $this->actingAs($this->owner)->putJson('/api/v1/settings', ['notifications' => ['recipients' => 'owner@toko.id, salah-email']])
            ->assertUnprocessable()->assertJsonFragment(['notifications.recipients' => ['Alamat email salah-email tidak valid. Pisahkan beberapa email dengan koma.']]);
        $this->actingAs($this->owner)->putJson('/api/v1/settings', ['notifications' => ['recipients' => 'owner@toko.id, gudang@toko.id', 'daily_report' => true, 'low_stock' => true]])
            ->assertOk()->assertJsonPath('data.notifications.daily_report', true);
    }

    public function test_daily_report_and_low_stock_commands(): void
    {
        $this->artisan('pos:daily-report')->expectsOutputToContain('Tidak ada yang dikirim')->assertSuccessful();
        Mail::assertNothingSent();

        Setting::put('notifications.recipients', 'owner@toko.id, gudang@toko.id');
        Setting::put('notifications.daily_report', true);
        Setting::put('notifications.low_stock', true);

        $this->artisan('pos:daily-report 2026-10-15')->expectsOutputToContain('terkirim untuk 1 outlet')->assertSuccessful();
        Mail::assertSent(DailyReportMail::class, fn ($m) => $m->hasTo('owner@toko.id') && $m->hasTo('gudang@toko.id') && $m->date === '2026-10-15');

        $this->artisan('pos:low-stock-alert')->assertSuccessful();
        Mail::assertSent(LowStockMail::class, function (LowStockMail $m) {
            $names = array_column($m->items, 'name');
            sort($names);

            return $names === ['Gula', 'Kopi'] && str_contains($m->render(), 'Stok menipis di Kopi Senja');
        });
    }

    public function test_daily_report_renders_and_test_email_endpoint(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/settings/test-email')
            ->assertUnprocessable()->assertJsonFragment(['notifications.recipients' => ['Isi minimal satu email penerima.']]);

        Setting::put('notifications.recipients', 'owner@toko.id');
        $this->actingAs($this->owner)->postJson('/api/v1/settings/test-email')->assertOk()->assertJsonPath('data.sent', 1);
        Mail::assertSent(DailyReportMail::class, fn ($m) => str_contains($m->render(), 'Penjualan bersih'));

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->postJson('/api/v1/settings/test-email')->assertForbidden();
    }
}
