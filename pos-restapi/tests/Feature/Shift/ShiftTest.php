<?php

namespace Tests\Feature\Shift;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outlet = Outlet::factory()->create();
        $this->cashier = User::factory()->cashier()->create(['name' => 'Rina']);
        $this->cashier->outlets()->attach($this->outlet);
    }

    private function openShift(int $openingCash = 200000): Shift
    {
        $id = $this->actingAs($this->cashier)
            ->postJson('/api/v1/shifts/open', ['opening_cash' => $openingCash])
            ->assertCreated()
            ->json('data.id');

        return Shift::query()->findOrFail($id);
    }

    // ---- Buka shift ----

    public function test_current_is_null_before_open_with_suggested_opening_cash(): void
    {
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now()->subDay(), 'closed_at' => now()->subHours(10), 'opening_cash' => 100000, 'actual_cash' => 150000]);

        $this->actingAs($this->cashier)
            ->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('suggested_opening_cash', 150000);
    }

    public function test_open_shift_then_current_returns_it(): void
    {
        $this->openShift(200000);

        $this->actingAs($this->cashier)
            ->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_cash', 200000)
            ->assertJsonPath('data.user.name', 'Rina')
            ->assertJsonMissingPath('data.expected_cash')
            ->assertJsonPath('suggested_opening_cash', null);
    }

    public function test_cannot_open_two_shifts_in_same_outlet(): void
    {
        $this->openShift();

        $this->actingAs($this->cashier)
            ->postJson('/api/v1/shifts/open', ['opening_cash' => 0])
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Anda masih punya shift terbuka sejak'));
    }

    public function test_open_requires_outlet_access_and_cashier_capable_role(): void
    {
        $other = Outlet::factory()->create();
        $this->actingAs($this->cashier)
            ->postJson('/api/v1/shifts/open', ['opening_cash' => 0, 'outlet_id' => $other->id])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak ditugaskan di outlet ini.');

        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $warehouse->outlets()->attach($this->outlet);
        $this->actingAs($warehouse)
            ->postJson('/api/v1/shifts/open', ['opening_cash' => 0])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda tidak bisa membuka shift kasir.');
    }

    // ---- Kas masuk/keluar ----

    public function test_cash_movements_and_cash_out_audit(): void
    {
        $shift = $this->openShift();

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'in', 'amount' => 50000, 'note' => 'Tambah uang kembalian'])
            ->assertCreated()
            ->assertJsonPath('data.type_label', 'Kas Masuk');

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'out', 'amount' => 20000, 'note' => 'Beli es batu'])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['action' => 'cash.out', 'model_type' => 'shift', 'model_id' => $shift->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'cash.in']);
    }

    public function test_cash_movement_validation(): void
    {
        $shift = $this->openShift();

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'out', 'amount' => 0])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Nominal minimal 1.')
            ->assertJsonPath('errors.note.0', 'Tulis keterangan, mis. "Beli es batu" atau "Tambah uang kembalian".');
    }

    public function test_other_cashier_cannot_touch_shift_but_manager_can(): void
    {
        $shift = $this->openShift();

        $budi = User::factory()->cashier()->create();
        $budi->outlets()->attach($this->outlet);
        $this->actingAs($budi)
            ->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'in', 'amount' => 1, 'note' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Shift ini milik kasir lain.');

        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach($this->outlet);
        $this->actingAs($manager)->postJson("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => 200000])->assertOk();
    }

    // ---- Tutup shift ----

    public function test_close_computes_expected_cash_and_difference(): void
    {
        $shift = $this->openShift(200000);
        $this->actingAs($this->cashier)->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'in', 'amount' => 50000, 'note' => 'Tambah']);
        $this->actingAs($this->cashier)->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'out', 'amount' => 20000, 'note' => 'Es batu']);

        // Expected = 200.000 + 50.000 − 20.000 = 230.000; fisik 225.000 → selisih −5.000
        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => 225000, 'note' => 'Uang receh kurang'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash', 230000)
            ->assertJsonPath('data.actual_cash', 225000)
            ->assertJsonPath('data.difference', -5000)
            ->assertJsonPath('report.cash_in', 50000)
            ->assertJsonPath('report.cash_out', 20000)
            ->assertJsonPath('report.difference', -5000);

        $this->assertSame(-5000, AuditLog::query()->where('action', 'shift.closed')->sole()->after_json['difference']);
    }

    public function test_note_required_when_difference_not_zero(): void
    {
        $shift = $this->openShift(100000);

        // Kas fisik sudah diinput → angka sistem & selisih baru ditampilkan, catatan diminta
        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => 90000])
            ->assertStatus(422)
            ->assertJsonPath('errors.note.0', 'Kas fisik berbeda dari catatan sistem. Tulis catatan penyebab selisihnya.')
            ->assertJsonPath('expected_cash', 100000)
            ->assertJsonPath('difference', -10000);

        $this->assertTrue($shift->fresh()->isOpen());

        // Setiap hitungan yang tidak cocok tercatat, walau kasir lalu menghitung ulang
        $mismatch = AuditLog::query()->where('action', 'shift.count_mismatch')->sole();
        $this->assertSame(90000, $mismatch->after_json['actual_cash']);

        $this->actingAs($this->cashier)->postJson("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => 100000])->assertOk();
    }

    public function test_closed_shift_rejects_changes_and_new_shift_can_open(): void
    {
        $shift = $this->openShift(100000);
        $this->actingAs($this->cashier)->postJson("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => 100000])->assertOk();

        $this->actingAs($this->cashier)
            ->postJson("/api/v1/shifts/{$shift->id}/cash-movements", ['type' => 'in', 'amount' => 1, 'note' => 'x'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Shift ini sudah ditutup. Buka shift baru untuk melanjutkan.');

        $this->actingAs($this->cashier)->getJson('/api/v1/shifts/current')->assertJsonPath('suggested_opening_cash', 100000);
        $this->openShift();
    }

    public function test_show_report_hides_system_cash_while_open(): void
    {
        $shift = $this->openShift(100000);

        $this->actingAs($this->cashier)
            ->getJson("/api/v1/shifts/{$shift->id}")
            ->assertOk()
            ->assertJsonPath('report.opening_cash', 100000)
            ->assertJsonMissingPath('report.expected_cash')
            ->assertJsonMissingPath('data.expected_cash');
    }
}
