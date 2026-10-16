<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use App\Support\Integrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => null]);
        $this->owner = User::factory()->owner()->create();
    }

    private function save(array $payload)
    {
        return $this->actingAs($this->owner)->putJson('/api/v1/settings', $payload);
    }

    public function test_midtrans_key_is_encrypted_hidden_and_used_for_qris(): void
    {
        $this->save(['midtrans' => ['is_production' => false, 'server_key' => 'Mid-server-abc']])
            ->assertUnprocessable()->assertJsonFragment(['midtrans.server_key' => ['Server key sandbox diawali "SB-Mid-server-". Salin dari dashboard.sandbox.midtrans.com → Settings → Access Keys.']]);

        $data = $this->save(['midtrans' => ['is_production' => false, 'server_key' => 'SB-Mid-server-rahasia']])->assertOk()->json('data.midtrans');
        $this->assertSame(['is_production' => false, 'server_key_set' => true], $data);

        // Tersimpan terenkripsi, tidak pernah terbaca di GET /settings maupun audit log
        $this->assertNotSame('SB-Mid-server-rahasia', Setting::get('midtrans.server_key'));
        $this->assertSame('SB-Mid-server-rahasia', Integrations::midtransServerKey());
        $cashier = User::factory()->cashier()->create();
        $this->assertStringNotContainsString('rahasia', $this->actingAs($cashier)->getJson('/api/v1/settings')->getContent());
        $this->assertStringNotContainsString('rahasia', json_encode(AuditLog::query()->get()->toArray()));

        // Dipakai untuk QRIS
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '201', 'actions' => []])]);
        $outlet = Outlet::factory()->create();
        $this->actingAs($this->owner)->getJson('/api/v1/payments/gateway')->assertJsonPath('data.qris_dynamic', true);
        $this->actingAs($this->owner)->postJson('/api/v1/payments/qris', ['outlet_id' => $outlet->id, 'amount' => 10000])->assertCreated();
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-rahasia:')));

        // Kosong = tetap; remove = hapus
        $this->save(['midtrans' => ['server_key' => '']])->assertOk()->assertJsonPath('data.midtrans.server_key_set', true);
        $this->save(['midtrans' => ['remove_server_key' => true]])->assertOk()->assertJsonPath('data.midtrans.server_key_set', false);
        $this->assertNull(Integrations::midtransServerKey());
    }

    public function test_smtp_settings_are_applied_and_only_owner_can_change(): void
    {
        $this->save(['smtp' => ['host' => 'smtp.gmail.com', 'from_address' => '']])->assertUnprocessable()->assertJsonValidationErrors('smtp.from_address');
        $this->save(['smtp' => ['host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl', 'username' => 'toko@gmail.com', 'password' => 'app-pass', 'from_address' => 'toko@gmail.com', 'from_name' => 'Kopi Senja']])
            ->assertOk()->assertJsonPath('data.smtp.password_set', true)->assertJsonMissingPath('data.smtp.password');

        Integrations::applySmtp();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('app-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('Kopi Senja', config('mail.from.name'));

        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach(Outlet::factory()->create());
        $this->actingAs($manager)->putJson('/api/v1/settings', ['smtp' => ['host' => 'evil.example.com']])->assertUnprocessable();
    }
}
