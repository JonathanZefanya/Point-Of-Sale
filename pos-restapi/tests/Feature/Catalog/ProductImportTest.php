<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nama', 'kategori', 'sku', 'barcode', 'harga_jual', 'harga_modal', 'satuan', 'stok_minimum', 'lacak_stok', 'stok_awal'];

    private User $manager;

    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outlet = Outlet::factory()->create();
        $this->manager = User::factory()->manager()->create();
        $this->manager->outlets()->attach($this->outlet);
        Category::query()->create(['name' => 'Minuman', 'sort_order' => 1]);
    }

    /** @param list<list<mixed>> $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'produk.xlsx', null, null, true);
    }

    private function import(UploadedFile $file, bool $preview)
    {
        return $this->actingAs($this->manager)->post('/api/v1/products/import', ['file' => $file, 'preview' => $preview ? '1' : '0'], ['Accept' => 'application/json']);
    }

    public function test_preview_shows_errors_per_row_without_saving(): void
    {
        Product::factory()->create(['sku' => 'LAMA-1', 'barcode' => '111']);
        $file = $this->xlsx([
            ['Kopi Susu', 'minuman', '', '', 18000, 9000, 'gelas', 5, 'ya', 20],
            ['', 'Makanan', 'X-1', '', 'abc', '', '', '', 'mungkin', ''],
            ['Teh', '', 'X-1', '111', '8.000', '', '', '', '', ''],
            [],
            ['Produk Lama', '', 'lama-1', '', 'Rp 9.500', 10000, '', '', 'tidak', 5],
        ]);

        $data = $this->import($file, true)->assertOk()->json('data');

        $this->assertSame(['total' => 4, 'create' => 3, 'update' => 1, 'errors' => 2, 'new_categories' => ['Makanan']], $data['summary']);
        $rows = collect($data['rows'])->keyBy('row');
        $this->assertSame([], $rows[2]['errors']);
        $this->assertSame(['Nama wajib diisi.', 'Harga jual harus angka bulat tanpa desimal, mis. 15500.', 'Lacak stok harus "ya" atau "tidak".'], $rows[3]['errors']);
        $this->assertContains('SKU X-1 dipakai dua kali di file ini (baris 3).', $rows[4]['errors']);
        $this->assertContains('Barcode 111 sudah dipakai produk lain.', $rows[4]['errors']);
        // Baris kosong dilewati tetapi nomor baris Excel tetap benar
        $this->assertSame('update', $rows[6]['action']);
        $this->assertContains('Stok awal diabaikan karena produk sudah ada. Ubah stok lewat Penyesuaian Stok.', $rows[6]['warnings']);
        $this->assertContains('Harga jual lebih kecil dari harga modal.', $rows[6]['warnings']);

        $this->assertSame(1, Product::query()->count());
        $this->import($file, false)->assertUnprocessable()->assertJsonPath('summary.errors', 2);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_commit_creates_and_updates_with_initial_stock_and_new_category(): void
    {
        $existing = Product::factory()->create(['sku' => 'LAMA-1', 'name' => 'Nama Lama', 'sell_price' => 5000, 'cost_price' => 3000]);

        $this->import($this->xlsx([
            ['Kopi Susu', 'minuman', '', '', 18000, 9000, 'gelas', 5, 'ya', 20],
            ['Keripik', 'Snack', 'KRP-1', '899', '12.000', '', '', '', 'tidak', ''],
            ['Nama Baru', '', 'LAMA-1', '', 6000, '', '', '', '', ''],
        ]), false)->assertCreated()->assertJsonPath('data.summary.create', 2)->assertJsonPath('data.summary.update', 1);

        $kopi = Product::query()->where('name', 'Kopi Susu')->sole();
        $this->assertSame('Minuman', $kopi->category->name);
        $this->assertNotEmpty($kopi->sku); // SKU otomatis
        $this->assertSame(20, app(StockService::class)->quantity($this->outlet->id, $kopi->id));
        $this->assertSame('Snack', Product::query()->where('sku', 'KRP-1')->sole()->category->name);
        $this->assertFalse(Product::query()->where('sku', 'KRP-1')->sole()->track_stock);

        $existing->refresh();
        $this->assertSame('Nama Baru', $existing->name);
        $this->assertSame(6000, $existing->sell_price);
        $this->assertSame(3000, $existing->cost_price); // kolom kosong = nilai lama
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.imported']);
    }

    public function test_csv_with_semicolon_and_access(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, implode(';', self::HEADER)."\nAir Mineral;Minuman;;;5000;2000;botol;;ya;\n");
        $this->import(new UploadedFile($path, 'produk.csv', 'text/csv', null, true), true)
            ->assertOk()->assertJsonPath('data.summary.create', 1)->assertJsonPath('data.rows.0.errors', []);

        $cashier = User::factory()->cashier()->create();
        $cashier->outlets()->attach($this->outlet);
        $this->actingAs($cashier)->post('/api/v1/products/import', ['file' => $this->xlsx([])], ['Accept' => 'application/json'])->assertForbidden();
        $this->import($this->xlsx([]), true)->assertUnprocessable()->assertJsonPath('errors.file.0', 'File tidak berisi data produk.');
    }

    public function test_template_and_export_roundtrip_columns(): void
    {
        Product::factory()->create(['name' => 'Kopi', 'sku' => 'KOPI-1', 'sell_price' => 15000]);

        $this->actingAs($this->manager)->get('/api/v1/products/import/template')->assertOk()->assertDownload('template-import-produk.xlsx');
        $response = $this->actingAs($this->manager)->get('/api/v1/products/export')->assertOk();

        $reader = new Reader;
        $reader->open($response->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        $this->assertSame(self::HEADER, $rows[0]);
        $this->assertSame(['Kopi', 'KOPI-1', 15000], [$rows[1][0], $rows[1][2], $rows[1][4]]);
    }
}
