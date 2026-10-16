<?php

namespace Tests\Feature\User;

use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $jkt;

    private Outlet $bdg;

    private User $owner;

    private User $manager;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jkt = Outlet::factory()->create(['code' => 'JKT01']);
        $this->bdg = Outlet::factory()->create(['code' => 'BDG01']);

        $this->owner = User::factory()->owner()->create(['name' => 'Owner', 'email' => 'owner@pos.test']);
        $this->manager = User::factory()->manager()->create(['name' => 'Manajer JKT', 'email' => 'manajer@pos.test']);
        $this->cashier = User::factory()->cashier()->create(['name' => 'Rina', 'email' => 'kasir1@pos.test']);

        $this->manager->outlets()->attach($this->jkt);
        $this->cashier->outlets()->attach($this->jkt);
    }

    private function newCashierPayload(array $override = []): array
    {
        return [
            'name' => 'Budi',
            'email' => 'budi@pos.test',
            'password' => 'rahasia123',
            'pin' => '111111',
            'role' => 'cashier',
            'outlet_ids' => [$this->jkt->id],
            ...$override,
        ];
    }

    // ---- List & detail ----

    public function test_owner_lists_all_users_with_pagination_meta(): void
    {
        User::factory()->cashier()->create(['name' => 'Kasir Bandung'])->outlets()->attach($this->bdg);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/users?per_page=2&sort=name')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Kasir Bandung')
            ->assertExactJson([
                'data' => $this->json('GET', '/api/v1/users?per_page=2&sort=name')->json('data'),
                'meta' => ['current_page' => 1, 'per_page' => 2, 'total' => 4, 'last_page' => 2],
            ]);
    }

    public function test_list_supports_search_and_filters(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/v1/users?search=rin')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Rina');
        $this->getJson('/api/v1/users?role=manager')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Manajer JKT');
        $this->getJson("/api/v1/users?outlet_id={$this->jkt->id}")->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/users?sort=password')->assertStatus(422);
    }

    public function test_manager_only_sees_users_in_own_outlet(): void
    {
        User::factory()->cashier()->create(['name' => 'Kasir Bandung'])->outlets()->attach($this->bdg);

        $names = collect($this->actingAs($this->manager)->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        $this->assertEqualsCanonicalizing(['Manajer JKT', 'Rina'], $names->all());
    }

    public function test_cashier_cannot_access_user_management(): void
    {
        $this->actingAs($this->cashier)->getJson('/api/v1/users')->assertStatus(403);
        $this->actingAs($this->cashier)->postJson('/api/v1/users', $this->newCashierPayload())->assertStatus(403);
    }

    public function test_manager_cannot_view_user_from_other_outlet(): void
    {
        $other = User::factory()->cashier()->create();
        $other->outlets()->attach($this->bdg);

        $this->actingAs($this->manager)->getJson("/api/v1/users/{$other->id}")->assertStatus(403);
        $this->actingAs($this->manager)->getJson("/api/v1/users/{$this->cashier->id}")->assertOk()->assertJsonPath('data.name', 'Rina');
    }

    // ---- Tambah ----

    public function test_owner_creates_cashier_with_outlet_and_audit_log(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/users', $this->newCashierPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Budi')
            ->assertJsonPath('data.role', 'cashier')
            ->assertJsonPath('data.has_pin', true)
            ->assertJsonPath('data.outlets.0.code', 'JKT01');

        $budi = User::query()->findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('rahasia123', $budi->password));
        $this->assertTrue(Hash::check('111111', $budi->pin));

        $log = AuditLog::query()->where('action', 'user.created')->firstOrFail();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame([$this->jkt->id], $log->after_json['outlet_ids']);
        $this->assertArrayNotHasKey('password', $log->after_json);
    }

    public function test_cashier_requires_pin_and_outlet(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/users', $this->newCashierPayload(['pin' => null, 'outlet_ids' => []]))
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', 'PIN wajib diisi untuk kasir agar bisa login di perangkat kasir.')
            ->assertJsonPath('errors.outlet_ids.0', 'Pilih minimal satu outlet tempat karyawan bekerja.');
    }

    public function test_owner_role_does_not_need_outlet(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/users', $this->newCashierPayload(['role' => 'owner', 'pin' => null, 'outlet_ids' => []]))
            ->assertCreated();
    }

    public function test_email_must_be_unique_including_deleted_users(): void
    {
        $this->cashier->delete();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/users', $this->newCashierPayload(['email' => $this->cashier->email]))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email sudah dipakai. Gunakan yang lain.');
    }

    public function test_manager_can_create_cashier_in_own_outlet_only(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/users', $this->newCashierPayload())->assertCreated();

        $this->actingAs($this->manager)
            ->postJson('/api/v1/users', $this->newCashierPayload(['email' => 'x@pos.test', 'outlet_ids' => [$this->bdg->id]]))
            ->assertStatus(422)
            ->assertJsonPath('errors', fn ($e) => isset($e['outlet_ids.0']));
    }

    public function test_manager_cannot_create_manager_or_owner(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/users', $this->newCashierPayload(['role' => 'manager']))
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', 'Peran ini tidak bisa Anda berikan. Pilih Kasir atau Staf Gudang, atau minta pemilik toko.');
    }

    // ---- Ubah ----

    public function test_update_keeps_password_and_pin_when_left_blank(): void
    {
        $oldPassword = $this->cashier->password;
        $oldPin = $this->cashier->pin;

        $this->actingAs($this->owner)
            ->putJson("/api/v1/users/{$this->cashier->id}", ['name' => 'Rina S.', 'password' => '', 'pin' => null])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rina S.');

        $this->cashier->refresh();
        $this->assertSame($oldPassword, $this->cashier->password);
        $this->assertSame($oldPin, $this->cashier->pin);
    }

    public function test_update_can_change_pin_and_records_flag_in_audit(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/users/{$this->cashier->id}", ['pin' => '999999'])
            ->assertOk();

        $this->assertTrue(Hash::check('999999', $this->cashier->refresh()->pin));
        $log = AuditLog::query()->where('action', 'user.updated')->firstOrFail();
        $this->assertTrue($log->after_json['pin_changed']);
        $this->assertFalse($log->after_json['password_changed']);
    }

    public function test_manager_cannot_edit_owner_or_other_outlet_staff(): void
    {
        $this->owner->outlets()->attach($this->jkt);
        $this->actingAs($this->manager)
            ->putJson("/api/v1/users/{$this->owner->id}", ['name' => 'X'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak bisa mengubah akun Pemilik. Hubungi pemilik toko.');

        $other = User::factory()->cashier()->create();
        $other->outlets()->attach($this->bdg);
        $this->actingAs($this->manager)
            ->putJson("/api/v1/users/{$other->id}", ['name' => 'X'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Karyawan ini tidak ditugaskan di outlet Anda.');
    }

    public function test_manager_outlet_change_keeps_outlets_outside_their_scope(): void
    {
        $surabaya = Outlet::factory()->create();
        $this->cashier->outlets()->attach($this->bdg);
        $this->manager->outlets()->attach($surabaya);

        // Manager memindahkan Rina dari JKT ke Surabaya; outlet Bandung bukan wewenangnya
        $this->actingAs($this->manager)
            ->putJson("/api/v1/users/{$this->cashier->id}", ['outlet_ids' => [$surabaya->id]])
            ->assertOk();

        $this->assertEqualsCanonicalizing([$surabaya->id, $this->bdg->id], $this->cashier->outletIds());
    }

    public function test_user_cannot_change_own_role_or_deactivate_self(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/users/{$this->owner->id}", ['role' => 'cashier', 'is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', 'Anda tidak bisa mengubah peran akun sendiri.')
            ->assertJsonPath('errors.is_active.0', 'Anda tidak bisa menonaktifkan akun sendiri.');

        $this->actingAs($this->owner)
            ->putJson("/api/v1/users/{$this->owner->id}", ['name' => 'Pemilik Baru'])
            ->assertOk();
    }

    public function test_changing_role_to_cashier_requires_pin_if_none(): void
    {
        $warehouse = User::factory()->create(['role' => Role::Warehouse, 'pin' => null]);
        $warehouse->outlets()->attach($this->jkt);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/users/{$warehouse->id}", ['role' => 'cashier'])
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', 'PIN wajib diisi untuk kasir agar bisa login di perangkat kasir.');
    }

    public function test_deactivating_user_revokes_tokens(): void
    {
        $this->cashier->createToken('cashier');

        $this->actingAs($this->owner)
            ->putJson("/api/v1/users/{$this->cashier->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $this->cashier->tokens()->count());
    }

    // ---- Hapus ----

    public function test_delete_soft_deletes_revokes_tokens_and_blocks_login(): void
    {
        $this->cashier->createToken('cashier');

        $this->actingAs($this->owner)->deleteJson("/api/v1/users/{$this->cashier->id}")->assertNoContent();

        $this->assertSoftDeleted($this->cashier);
        $this->assertSame(0, $this->cashier->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted', 'model_id' => $this->cashier->id]);

        $this->postJson('/api/v1/auth/pin-login', ['user_id' => $this->cashier->id, 'pin' => '123456'])->assertStatus(422);
        $this->actingAs($this->owner)->getJson("/api/v1/users/{$this->cashier->id}")->assertStatus(404);
    }

    public function test_user_cannot_delete_self(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/users/{$this->owner->id}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak bisa menghapus akun sendiri. Minta pemilik lain untuk melakukannya.');
    }

    public function test_last_active_owner_cannot_be_removed(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Ini satu-satunya akun Pemilik yang aktif.');

        app(UserService::class)->delete($this->owner);
    }

    // ---- Peran ----

    public function test_roles_marks_assignable_for_current_user(): void
    {
        $roles = collect($this->actingAs($this->manager)->getJson('/api/v1/roles')->assertOk()->json('data'))
            ->pluck('assignable', 'value');

        $this->assertSame(['owner' => false, 'manager' => false, 'cashier' => true, 'warehouse' => true], $roles->all());
    }
}
