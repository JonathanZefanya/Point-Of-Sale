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

class SaleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    private User $manager;

    private Shift $shift;

    private Product $kopi;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', false);

        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->cashier = User::factory()->cashier()->create(['name' => 'Rina']);
        $this->manager = User::factory()->manager()->create(['pin' => '999999']);
        $this->cashier->outlets()->attach($this->outlet);
        $this->manager->outlets()->attach($this->outlet);
        $this->shift = Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);

        $this->kopi = Product::factory()->create(['name' => 'Kopi', 'sell_price' => 15000]);
        app(StockService::class)->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
    }

    private function checkout(array $extra = []): Sale
    {
        $id = $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 45000]],
            ...$extra,
        ])->assertSuccessful()->json('data.id');

        return Sale::query()->findOrFail($id);
    }

    private function stock(): int
    {
        return app(StockService::class)->quantity($this->outlet->id, $this->kopi->id);
    }

    // ---- Hold & lanjutkan (F4) ----

    public function test_hold_saves_cart_without_number_payment_or_stock_change(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales/hold', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 2]], 'hold_label' => 'Meja 3'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'held')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.hold_label', 'Meja 3')
            ->assertJsonPath('data.grand_total', 30000);

        $this->assertSame(10, $this->stock());

        $this->actingAs($this->cashier)->getJson('/api/v1/sales/held')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.items.0.quantity', 2);
    }

    public function test_resume_held_sale_completes_same_row_and_removes_it_from_held(): void
    {
        $heldId = $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales/hold', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 1]], 'hold_label' => 'Meja 3'])
            ->json('data.id');

        // Kasir menambah item lalu membayar
        $sale = $this->checkout(['held_sale_id' => $heldId]);

        $this->assertSame($heldId, $sale->id);
        $this->assertSame('completed', $sale->status->value);
        $this->assertSame(1, $sale->items()->count());
        $this->assertSame(3, $sale->items()->first()->quantity);
        $this->assertSame(7, $this->stock());
        $this->actingAs($this->cashier)->getJson('/api/v1/sales/held')->assertJsonCount(0, 'data');

        // Membuka transaksi tersimpan yang sama dua kali → ditolak
        $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 15000]],
            'held_sale_id' => $heldId,
        ])->assertStatus(409);
    }

    public function test_discard_held_sale(): void
    {
        $heldId = $this->actingAs($this->cashier)
            ->postJson('/api/v1/sales/hold', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 1]]])
            ->json('data.id');

        $this->actingAs($this->cashier)->deleteJson("/api/v1/sales/held/{$heldId}")->assertNoContent();
        $this->assertSame(0, Sale::query()->count());

        // Transaksi selesai tidak bisa dihapus lewat endpoint ini
        $sale = $this->checkout();
        $this->actingAs($this->cashier)->deleteJson("/api/v1/sales/held/{$sale->id}")->assertStatus(404);
    }

    // ---- Riwayat & detail ----

    public function test_history_lists_completed_sales_scoped_to_outlet(): void
    {
        $sale = $this->checkout();
        $this->actingAs($this->cashier)->postJson('/api/v1/sales/hold', ['items' => [['product_id' => $this->kopi->id, 'quantity' => 1]]]);

        $other = Outlet::factory()->create();
        $otherCashier = User::factory()->cashier()->create();
        $otherCashier->outlets()->attach($other);

        $this->actingAs($this->cashier)->getJson('/api/v1/sales')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', $sale->number)
            ->assertJsonPath('data.0.cashier.name', 'Rina');

        $this->actingAs($this->cashier)->getJson("/api/v1/sales?search={$sale->number}&shift_id={$this->shift->id}")->assertJsonPath('meta.total', 1);
        $this->actingAs($otherCashier)->getJson('/api/v1/sales')->assertJsonPath('meta.total', 0);
        $this->actingAs($otherCashier)->getJson("/api/v1/sales/{$sale->id}")->assertStatus(403);
        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$sale->id}")->assertOk()->assertJsonPath('data.items.0.product_name', 'Kopi');
    }

    // ---- Void (F5) ----

    public function test_void_with_supervisor_pin_returns_stock_and_is_audited(): void
    {
        $sale = $this->checkout();
        $this->assertSame(7, $this->stock());

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Pelanggan batal beli', 'supervisor_pin' => '999999'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.void_reason', 'Pelanggan batal beli')
            ->assertJsonPath('data.voided_by.id', $this->manager->id);

        $this->assertSame(10, $this->stock());
        $log = AuditLog::query()->where('action', 'sale.voided')->sole();
        $this->assertSame($this->cashier->id, $log->user_id);
        $this->assertSame($this->manager->id, $log->after_json['approved_by']);

        // Void tidak dihitung di kas seharusnya
        $this->actingAs($this->cashier)->postJson("/api/v1/shifts/{$this->shift->id}/close", ['actual_cash' => 0])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', 0)
            ->assertJsonPath('report.transaction_count', 0);
    }

    public function test_void_rejects_wrong_pin_and_locks_after_five_attempts(): void
    {
        $sale = $this->checkout();

        // PIN kasir sendiri bukan PIN supervisor
        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('errors.supervisor_pin.0', 'PIN supervisor salah atau bukan milik pemilik/manajer outlet ini.');

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '000000']);
        }

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '999999'])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        $this->assertSame('completed', $sale->fresh()->status->value);
    }

    public function test_void_requires_reason_and_open_shift_and_only_once(): void
    {
        $sale = $this->checkout();

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['supervisor_pin' => '999999'])
            ->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'Tulis alasan pembatalan, mis. "Pelanggan batal beli".');

        $this->actingAs($this->cashier)->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '999999'])->assertOk();
        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '999999'])
            ->assertStatus(409)
            ->assertJsonPath('message', "Transaksi {$sale->number} berstatus Dibatalkan dan tidak bisa dibatalkan.");

        $second = $this->checkout();
        $this->shift->update(['closed_at' => now()]);
        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$second->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '999999'])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'shift yang sudah ditutup'));
    }

    public function test_supervisor_from_other_outlet_cannot_approve(): void
    {
        $sale = $this->checkout();
        $otherManager = User::factory()->manager()->create(['pin' => '555555']);
        $otherManager->outlets()->attach(Outlet::factory()->create());

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Batal', 'supervisor_pin' => '555555'])
            ->assertStatus(422);
    }

    // ---- PIN supervisor unik ----

    public function test_supervisor_pin_must_be_unique(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/users', [
                'name' => 'Manajer Baru', 'email' => 'm2@pos.test', 'password' => 'rahasia123',
                'pin' => '999999', 'role' => 'manager', 'outlet_ids' => [$this->outlet->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', 'PIN ini sudah dipakai pemilik/manajer lain. Pilih PIN lain.');

        // Kasir boleh memakai PIN yang sama (tidak dipakai untuk persetujuan)
        $this->actingAs($owner)
            ->postJson('/api/v1/users', [
                'name' => 'Kasir Baru', 'email' => 'k2@pos.test', 'password' => 'rahasia123',
                'pin' => '999999', 'role' => 'cashier', 'outlet_ids' => [$this->outlet->id],
            ])
            ->assertCreated();

        // Manajer mengubah data sendiri dengan PIN yang sama → bukan bentrok
        $this->actingAs($this->manager)->putJson("/api/v1/users/{$this->manager->id}", ['pin' => '999999'])->assertOk();
    }
}
