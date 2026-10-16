<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_user_reads_nested_settings_with_defaults(): void
    {
        Setting::put('tax.inclusive', true);

        $this->actingAs(User::factory()->cashier()->create())
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.tax.inclusive', true)
            ->assertJsonPath('data.tax.enabled', true)          // default dari SettingSeeder
            ->assertJsonPath('data.rounding.to', 100)
            ->assertJsonPath('data.receipt.paper_width', 58);
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/v1/settings')->assertStatus(401);
    }
}
