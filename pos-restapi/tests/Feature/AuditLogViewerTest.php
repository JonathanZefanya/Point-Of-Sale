<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create(['name' => 'Budi']);
        $this->manager = User::factory()->manager()->create(['name' => 'Sari']);
        $this->manager->outlets()->attach(Outlet::factory()->create());
    }

    private function logAs(User $user, string $action, $model = null, string $at = '2026-10-15 10:00:00'): void
    {
        $this->actingAs($user);
        $log = app(AuditLogger::class)->log($action, $model, ['sell_price' => 15000], ['sell_price' => 18000]);
        $log->forceFill(['created_at' => $at])->save();
    }

    public function test_owner_lists_logs_with_labels_and_filters(): void
    {
        $product = Product::factory()->create(['name' => 'Kopi Susu']);
        $this->logAs($this->manager, 'product.price_changed', $product, '2026-10-14 09:00:00');
        $this->logAs($this->owner, 'settings.updated');
        $product->delete(); // subjek yang sudah dihapus tetap bernama

        $this->actingAs($this->owner)->getJson('/api/v1/audit-logs')->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.action_label', 'Pengaturan diubah')
            ->assertJsonPath('data.1.action_label', 'Harga produk diubah')
            ->assertJsonPath('data.1.subject', 'Produk Kopi Susu')
            ->assertJsonPath('data.1.user.name', 'Sari')
            ->assertJsonPath('data.1.before.sell_price', 15000);

        $this->actingAs($this->owner)->getJson("/api/v1/audit-logs?user_id={$this->manager->id}")->assertJsonPath('meta.total', 1);
        $this->actingAs($this->owner)->getJson('/api/v1/audit-logs?action=settings.updated')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->owner)->getJson('/api/v1/audit-logs?from=2026-10-15&to=2026-10-15')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.action', 'settings.updated');

        $filters = $this->actingAs($this->owner)->getJson('/api/v1/audit-logs/filters')->assertOk()->json('data');
        $this->assertSame(['Budi', 'Sari'], array_column($filters['users'], 'name'));
        $this->assertContains(['value' => 'product.price_changed', 'label' => 'Harga produk diubah'], $filters['actions']);
    }

    public function test_only_owner_can_view_and_logs_are_read_only(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->actingAs($this->manager)->getJson('/api/v1/audit-logs/filters')->assertForbidden();
        $this->actingAs($this->owner)->deleteJson('/api/v1/audit-logs/1')->assertNotFound(); // tidak ada endpoint ubah/hapus
        $this->assertSame(0, AuditLog::query()->count());
    }
}
