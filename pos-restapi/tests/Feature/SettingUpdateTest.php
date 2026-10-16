<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outlet = Outlet::factory()->create(['tax_rate' => 10]);
    }

    public function test_owner_updates_global_and_outlet_settings_with_audit(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->putJson('/api/v1/settings', [
                'business' => ['name' => 'Kopi Rina', 'npwp' => '01.234.567.8-901.000'],
                'tax' => ['inclusive' => true],
                'rounding' => ['enabled' => true, 'to' => 500, 'mode' => 'up'],
                'receipt' => ['paper_width' => 80],
                'outlet' => ['id' => $this->outlet->id, 'tax_rate' => 11, 'receipt_footer' => 'Terima kasih!'],
            ])
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Kopi Rina')
            ->assertJsonPath('data.tax.inclusive', true)
            ->assertJsonPath('data.rounding.to', 500)
            ->assertJsonPath('data.receipt.paper_width', 80);

        $this->assertSame(11.0, $this->outlet->fresh()->tax_rate);
        $this->assertSame('Terima kasih!', $this->outlet->fresh()->receipt_footer);

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();
        $this->assertFalse($log->before_json['tax.inclusive']);
        $this->assertTrue($log->after_json['tax.inclusive']);
        $this->assertEquals(11, $log->after_json["outlet.{$this->outlet->id}.tax_rate"]);
    }

    public function test_unchanged_values_are_not_audited(): void
    {
        $owner = User::factory()->owner()->create();
        Setting::put('tax.enabled', true);

        $this->actingAs($owner)->putJson('/api/v1/settings', ['tax' => ['enabled' => true]])->assertOk();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_manager_can_only_change_own_outlet_section(): void
    {
        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach($this->outlet);

        $this->actingAs($manager)
            ->putJson('/api/v1/settings', ['outlet' => ['id' => $this->outlet->id, 'receipt_header' => 'Cabang Sudirman']])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson('/api/v1/settings', ['tax' => ['enabled' => false]])
            ->assertStatus(422)
            ->assertJsonPath('errors.business.0', 'Hanya pemilik yang bisa mengubah pengaturan bisnis, pajak, pembulatan, dan struk.');

        $other = Outlet::factory()->create();
        $this->actingAs($manager)
            ->putJson('/api/v1/settings', ['outlet' => ['id' => $other->id, 'tax_rate' => 0]])
            ->assertStatus(422);
    }

    public function test_validation_and_cashier_forbidden(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->putJson('/api/v1/settings', ['rounding' => ['to' => 250], 'receipt' => ['paper_width' => 100], 'outlet' => ['id' => $this->outlet->id, 'tax_rate' => 150]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rounding.to', 'receipt.paper_width', 'outlet.tax_rate']);

        $this->actingAs(User::factory()->cashier()->create())->putJson('/api/v1/settings', [])->assertStatus(403);
    }
}
