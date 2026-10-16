<?php

namespace Tests\Feature\Outlet;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_all_outlets_sorted_by_code(): void
    {
        Outlet::factory()->create(['code' => 'JKT01']);
        Outlet::factory()->create(['code' => 'BDG01']);

        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/v1/outlets')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'BDG01')
            ->assertJsonPath('data.1.code', 'JKT01')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_other_roles_only_see_assigned_outlets(): void
    {
        $jkt = Outlet::factory()->create(['code' => 'JKT01']);
        Outlet::factory()->create(['code' => 'BDG01']);
        $manager = User::factory()->manager()->create();
        $manager->outlets()->attach($jkt);

        $this->actingAs($manager)
            ->getJson('/api/v1/outlets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'JKT01');
    }

    public function test_search_by_code_or_name(): void
    {
        Outlet::factory()->create(['code' => 'JKT01', 'name' => 'Toko Pusat']);
        Outlet::factory()->create(['code' => 'BDG01', 'name' => 'Cabang Dago']);

        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/v1/outlets?search=dago')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BDG01');
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/v1/outlets')->assertStatus(401);
    }
}
