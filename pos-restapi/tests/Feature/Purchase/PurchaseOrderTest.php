<?php

namespace Tests\Feature\Purchase;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private User $warehouse;

    private Supplier $supplier;

    private Product $kopi;

    private Product $gula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
        $this->outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $this->warehouse = User::factory()->create(['role' => Role::Warehouse]);
        $this->warehouse->outlets()->attach($this->outlet);
        $this->supplier = Supplier::query()->create(['name' => 'PT Kopi Nusantara', 'phone' => '0215550000']);

        $this->kopi = Product::factory()->create(['name' => 'Biji Kopi 1kg', 'cost_price' => 100000]);
        $this->gula = Product::factory()->create(['name' => 'Gula 1kg', 'cost_price' => 15000]);
        app(StockService::class)->change($this->outlet->id, $this->kopi, 10, StockMovementType::Initial);
    }

    private function createPo(string $status = 'ordered'): array
    {
        return $this->actingAs($this->warehouse)->postJson('/api/v1/purchase-orders', [
            'outlet_id' => $this->outlet->id,
            'supplier_id' => $this->supplier->id,
            'status' => $status,
            'items' => [
                ['product_id' => $this->kopi->id, 'quantity' => 10, 'cost_price' => 130000],
                ['product_id' => $this->gula->id, 'quantity' => 20, 'cost_price' => 16000],
            ],
        ])->assertCreated()->json('data');
    }

    private function stock(Product $p): int
    {
        return app(StockService::class)->quantity($this->outlet->id, $p->id);
    }

    public function test_supplier_crud_and_cashier_forbidden(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->getJson('/api/v1/suppliers')->assertForbidden();
        $this->actingAs($cashier)->postJson('/api/v1/suppliers', ['name' => 'X'])->assertForbidden();

        $id = $this->actingAs($this->warehouse)->postJson('/api/v1/suppliers', ['name' => 'CV Gula Manis', 'phone' => 'abc'])
            ->assertUnprocessable()->assertJsonPath('errors.phone.0', 'Nomor telepon hanya boleh angka, spasi, +, dan -.');
        $id = $this->actingAs($this->warehouse)->postJson('/api/v1/suppliers', ['name' => 'CV Gula Manis', 'phone' => '0812 3456'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->warehouse)->putJson("/api/v1/suppliers/{$id}", ['name' => 'CV Gula Manis Jaya'])->assertOk()->assertJsonPath('data.name', 'CV Gula Manis Jaya');
        $this->actingAs($this->warehouse)->getJson('/api/v1/suppliers?search=gula')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->warehouse)->deleteJson("/api/v1/suppliers/{$id}")->assertNoContent();
        $this->assertSoftDeleted('suppliers', ['id' => $id]);
    }

    public function test_create_po_with_number_snapshot_and_total_without_touching_stock(): void
    {
        $po = $this->createPo();

        $this->assertSame('PO-JKT01-20261015-0001', $po['number']);
        $this->assertSame('ordered', $po['status']);
        $this->assertSame(10 * 130000 + 20 * 16000, $po['total']);
        $this->assertSame('Biji Kopi 1kg', $po['items'][0]['product_name']);
        $this->assertSame(10, $this->stock($this->kopi)); // stok belum berubah sebelum barang diterima

        $this->assertSame('PO-JKT01-20261015-0002', $this->createPo('draft')['number']);
    }

    public function test_po_validation(): void
    {
        $this->actingAs($this->warehouse)->postJson('/api/v1/purchase-orders', ['supplier_id' => $this->supplier->id, 'status' => 'ordered', 'items' => []])
            ->assertUnprocessable()->assertJsonPath('errors.items.0', 'Tambahkan minimal satu produk ke PO.');
        $this->actingAs($this->warehouse)->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id, 'status' => 'ordered',
            'items' => [['product_id' => $this->kopi->id, 'quantity' => 1, 'cost_price' => 1], ['product_id' => $this->kopi->id, 'quantity' => 2, 'cost_price' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
    }

    public function test_full_receive_adds_stock_updates_average_cost_and_status(): void
    {
        $po = $this->createPo();
        $items = collect($po['items']);

        $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/receive", [
            'items' => $items->map(fn ($i) => ['id' => $i['id'], 'quantity' => $i['remaining']])->all(),
        ])->assertOk()->assertJsonPath('data.status', 'received')->assertJsonPath('data.items.0.received_quantity', 10);

        $this->assertSame(20, $this->stock($this->kopi));
        $this->assertSame(20, $this->stock($this->gula));
        // Rata-rata: (10 × 100.000 + 10 × 130.000) / 20 = 115.000; gula tanpa stok lama → harga beli
        $this->assertSame(115000, $this->kopi->fresh()->cost_price);
        $this->assertSame(16000, $this->gula->fresh()->cost_price);

        $movement = StockMovement::query()->where('product_id', $this->kopi->id)->where('type', 'purchase')->sole();
        $this->assertSame(130000, $movement->cost_price); // kartu stok mencatat harga beli
        $this->assertSame('purchase_order', $movement->reference_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order.received', 'model_id' => $po['id']]);

        $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/receive", ['items' => [['id' => $items[0]['id'], 'quantity' => 1]]])
            ->assertConflict();
    }

    public function test_partial_receive_and_over_receive_rejected(): void
    {
        $po = $this->createPo();
        [$kopi, $gula] = $po['items'];

        $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/receive", [
            'items' => [['id' => $kopi['id'], 'quantity' => 4], ['id' => $gula['id'], 'quantity' => 0]],
        ])->assertOk()->assertJsonPath('data.status', 'partial')->assertJsonPath('data.items.0.remaining', 6);
        $this->assertSame(14, $this->stock($this->kopi));

        $errors = $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/receive", ['items' => [['id' => $kopi['id'], 'quantity' => 7]]])
            ->assertUnprocessable()->json('errors');
        $this->assertSame(['Biji Kopi 1kg: maksimal 6 lagi (dipesan 10, sudah diterima 4).'], $errors["items.{$kopi['id']}"]);
        $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/receive", ['items' => [['id' => $kopi['id'], 'quantity' => 0]]])
            ->assertUnprocessable()->assertJsonPath('errors.items.0', 'Isi jumlah diterima minimal untuk satu produk.');
        $this->assertSame(14, $this->stock($this->kopi));

        // PO yang sudah menerima barang tidak bisa diubah/dibatalkan
        $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/cancel")->assertConflict();
    }

    public function test_update_and_cancel_ordered_po_and_outlet_scope(): void
    {
        $po = $this->createPo('draft');
        $this->actingAs($this->warehouse)->putJson("/api/v1/purchase-orders/{$po['id']}", [
            'supplier_id' => $this->supplier->id, 'status' => 'ordered',
            'items' => [['product_id' => $this->gula->id, 'quantity' => 5, 'cost_price' => 15500]],
        ])->assertOk()->assertJsonPath('data.status', 'ordered')->assertJsonCount(1, 'data.items')->assertJsonPath('data.total', 77500);

        $this->actingAs($this->warehouse)->postJson("/api/v1/purchase-orders/{$po['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(1, PurchaseOrder::query()->where('status', 'cancelled')->count());

        $other = User::factory()->create(['role' => Role::Warehouse]);
        $other->outlets()->attach(Outlet::factory()->create());
        $this->actingAs($other)->getJson("/api/v1/purchase-orders/{$po['id']}")->assertForbidden();
        $this->actingAs($other)->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->warehouse)->getJson('/api/v1/purchase-orders?status=cancelled')->assertOk()->assertJsonCount(1, 'data');
    }
}
