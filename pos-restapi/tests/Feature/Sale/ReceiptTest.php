<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('tax.enabled', true);
        Setting::put('business.name', 'Kopi Rina');
        Setting::put('receipt.paper_width', 80);

        $this->outlet = Outlet::factory()->create([
            'code' => 'JKT01', 'tax_rate' => 10, 'address' => 'Jl. Sudirman 1', 'phone' => '021-1',
            'receipt_header' => null, 'receipt_footer' => "Terima kasih\nSampai jumpa",
        ]);
        $this->cashier = User::factory()->cashier()->create(['name' => 'Rina']);
        $this->cashier->outlets()->attach($this->outlet);
        Shift::query()->create(['outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => 0]);
    }

    private function sell(): int
    {
        $product = Product::factory()->create(['name' => 'Kopi Susu', 'sell_price' => 15000]);
        app(StockService::class)->change($this->outlet->id, $product, 5, StockMovementType::Initial);

        return $this->actingAs($this->cashier)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'note' => 'Es sedikit']],
            'payments' => [['method' => 'cash', 'amount' => 50000]],
        ])->json('data.id');
    }

    public function test_receipt_contains_ready_to_print_lines(): void
    {
        $id = $this->sell();

        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.paper_width', 80)
            ->assertJsonPath('data.header', ['Kopi Rina', 'Jl. Sudirman 1', 'Telp 021-1'])
            ->assertJsonPath('data.cashier', 'Rina')
            ->assertJsonPath('data.items.0.name', 'Kopi Susu')
            ->assertJsonPath('data.items.0.note', 'Es sedikit')
            ->assertJsonPath('data.totals', [['label' => 'Subtotal', 'amount' => 30000], ['label' => 'Pajak', 'amount' => 3000]])
            ->assertJsonPath('data.grand_total', 33000)
            ->assertJsonPath('data.payments', [['label' => 'Tunai', 'amount' => 50000]])
            ->assertJsonPath('data.change_amount', 17000)
            ->assertJsonPath('data.footer', ['Terima kasih', 'Sampai jumpa'])
            ->assertJsonPath('data.is_void', false);
    }

    public function test_custom_header_replaces_outlet_details_and_cashier_can_be_hidden(): void
    {
        $this->outlet->update(['receipt_header' => "KOPI RINA\nCabang Sudirman"]);
        Setting::put('receipt.show_cashier', false);
        $id = $this->sell();

        $this->actingAs($this->cashier)->getJson("/api/v1/sales/{$id}/receipt")
            ->assertJsonPath('data.header', ['KOPI RINA', 'Cabang Sudirman'])
            ->assertJsonPath('data.cashier', null);
    }

    public function test_receipt_scoped_to_outlet_access(): void
    {
        $id = $this->sell();
        $stranger = User::factory()->cashier()->create();
        $stranger->outlets()->attach(Outlet::factory()->create());

        $this->actingAs($stranger)->getJson("/api/v1/sales/{$id}/receipt")->assertStatus(403);
    }
}
