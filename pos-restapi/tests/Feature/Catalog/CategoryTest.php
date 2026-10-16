<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create();
    }

    public function test_list_returns_all_sorted_with_product_count(): void
    {
        $snack = Category::factory()->create(['name' => 'Snack', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'Minuman', 'sort_order' => 1]);
        Product::factory()->count(2)->create(['category_id' => $snack->id]);

        $this->actingAs(User::factory()->cashier()->create())
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Minuman')
            ->assertJsonPath('data.1.name', 'Snack')
            ->assertJsonPath('data.1.products_count', 2);
    }

    public function test_manager_creates_category_at_end_of_order(): void
    {
        Category::factory()->create(['sort_order' => 4]);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/categories', ['name' => '  Kopi  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kopi')
            ->assertJsonPath('data.sort_order', 5);
    }

    public function test_name_must_be_unique(): void
    {
        Category::factory()->create(['name' => 'Minuman']);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/categories', ['name' => 'Minuman'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Kategori dengan nama ini sudah ada.');
    }

    public function test_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Minuman']);

        $this->actingAs($this->manager)
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Minuman', 'sort_order' => 3])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 3);
    }

    public function test_cannot_delete_category_in_use(): void
    {
        $category = Category::factory()->create(['name' => 'Minuman']);
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $this->actingAs($this->manager)
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Kategori Minuman masih dipakai 3 produk. Pindahkan produknya ke kategori lain terlebih dahulu.');
    }

    public function test_delete_category_only_used_by_deleted_products(): void
    {
        $category = Category::factory()->create();
        $old = Product::factory()->create(['category_id' => $category->id]);
        $old->delete();

        $this->actingAs($this->manager)->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();

        $this->assertNull($old->fresh()->category_id);
    }

    public function test_cashier_cannot_modify_categories(): void
    {
        $category = Category::factory()->create();
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->postJson('/api/v1/categories', ['name' => 'X'])->assertStatus(403);
        $this->actingAs($cashier)->putJson("/api/v1/categories/{$category->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAs($cashier)->deleteJson("/api/v1/categories/{$category->id}")->assertStatus(403);
    }
}
