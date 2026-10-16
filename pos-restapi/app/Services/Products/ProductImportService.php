<?php

namespace App\Services\Products;

use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;

/**
 * Import produk dari Excel/CSV (2.10, F7.4): pratinjau dengan error per baris → konfirmasi.
 * SKU yang sudah ada = perbarui produk itu; selain itu produk baru. Semua baris disimpan atau tidak sama sekali.
 */
class ProductImportService
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly ProductService $products,
        private readonly CategoryService $categories,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $raw  hasil ProductSpreadsheet::read
     * @return array{rows: list<array<string, mixed>>, summary: array{total: int, create: int, update: int, errors: int, new_categories: list<string>}}
     */
    public function preview(array $raw): array
    {
        if (! $raw) {
            throw new BusinessException('File tidak berisi data produk. Isi mulai baris ke-2 di bawah judul kolom, sesuai template.', 422, ['errors' => ['file' => ['File tidak berisi data produk.']]]);
        }
        if (count($raw) > self::MAX_ROWS) {
            throw new BusinessException('Maksimal '.self::MAX_ROWS.' produk per file. Bagi menjadi beberapa file.', 422, ['errors' => ['file' => ['Terlalu banyak baris.']]]);
        }

        $skus = array_filter(array_map(fn ($r) => $this->text($r['sku'] ?? null), $raw));
        // SKU dicocokkan tanpa membedakan huruf besar/kecil (sama di SQLite & MySQL)
        $existing = Product::withTrashed()->whereIn(DB::raw('UPPER(sku)'), array_map('strtoupper', $skus))->get()->keyBy(fn ($p) => strtoupper($p->sku));
        $barcodes = Product::query()->whereIn('barcode', array_filter(array_map(fn ($r) => $this->text($r['barcode'] ?? null), $raw)))->pluck('id', 'barcode');
        $categories = Category::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);

        $rows = [];
        $seenSku = $seenBarcode = $newCategories = [];
        foreach ($raw as $line => $r) {
            $errors = [];
            $warnings = [];
            $name = $this->text($r['nama'] ?? null);
            $sku = $this->text($r['sku'] ?? null);
            $barcode = $this->text($r['barcode'] ?? null);
            $category = $this->text($r['kategori'] ?? null);
            $target = $sku ? $existing->get(strtoupper($sku)) : null;

            if (! $name) {
                $errors[] = 'Nama wajib diisi.';
            } elseif (mb_strlen($name) > 150) {
                $errors[] = 'Nama maksimal 150 karakter.';
            }
            $sell = $this->integer($r['harga_jual'] ?? null, 'Harga jual', $errors, required: true);
            $cost = $this->integer($r['harga_modal'] ?? null, 'Harga modal', $errors);
            $minStock = $this->integer($r['stok_minimum'] ?? null, 'Stok minimum', $errors);
            $initial = $this->integer($r['stok_awal'] ?? null, 'Stok awal', $errors);
            $track = $this->boolean($r['lacak_stok'] ?? null, $errors);
            $unit = $this->text($r['satuan'] ?? null);

            if ($sku && ! preg_match('/^[A-Za-z0-9._-]+$/', $sku)) {
                $errors[] = 'SKU hanya boleh huruf, angka, titik, strip, dan garis bawah.';
            }
            if ($sku && isset($seenSku[strtoupper($sku)])) {
                $errors[] = "SKU {$sku} dipakai dua kali di file ini (baris {$seenSku[strtoupper($sku)]}).";
            }
            if ($target?->trashed()) {
                $errors[] = "SKU {$sku} milik produk yang sudah dihapus. Pakai SKU lain.";
            }
            if ($barcode && isset($seenBarcode[$barcode])) {
                $errors[] = "Barcode {$barcode} dipakai dua kali di file ini (baris {$seenBarcode[$barcode]}).";
            } elseif ($barcode && isset($barcodes[$barcode]) && $barcodes[$barcode] !== $target?->id) {
                $errors[] = "Barcode {$barcode} sudah dipakai produk lain.";
            }
            if ($unit && mb_strlen($unit) > 20) {
                $errors[] = 'Satuan maksimal 20 karakter.';
            }
            if ($category && ! isset($categories[mb_strtolower($category)]) && ! in_array($category, $newCategories, true)) {
                $newCategories[] = $category;
            }
            if ($target?->variants()->exists()) {
                $warnings[] = 'Produk ini punya varian. Harga & stok varian tidak diubah lewat import.';
            }
            if ($target && $initial) {
                $warnings[] = 'Stok awal diabaikan karena produk sudah ada. Ubah stok lewat Penyesuaian Stok.';
            }
            if ($sell !== null && $cost !== null && $sell < $cost) {
                $warnings[] = 'Harga jual lebih kecil dari harga modal.';
            }

            if ($sku) {
                $seenSku[strtoupper($sku)] = $line;
            }
            if ($barcode) {
                $seenBarcode[$barcode] = $line;
            }
            $rows[] = [
                'row' => $line,
                'action' => $target ? 'update' : 'create',
                'product_id' => $target?->id,
                'data' => compact('name', 'category', 'sku', 'barcode', 'sell', 'cost', 'unit', 'minStock', 'track', 'initial'),
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        return ['rows' => $rows, 'summary' => [
            'total' => count($rows),
            'create' => count(array_filter($rows, fn ($r) => $r['action'] === 'create')),
            'update' => count(array_filter($rows, fn ($r) => $r['action'] === 'update')),
            'errors' => count(array_filter($rows, fn ($r) => $r['errors'])),
            'new_categories' => $newCategories,
        ]];
    }

    /** Simpan semua baris (atau tidak sama sekali bila ada error). */
    public function commit(array $raw, int $outletId): array
    {
        $preview = $this->preview($raw);
        if ($preview['summary']['errors'] > 0) {
            throw new BusinessException("Ada {$preview['summary']['errors']} baris yang perlu diperbaiki. Tidak ada produk yang disimpan.", 422, $preview);
        }

        DB::transaction(function () use ($preview, $outletId) {
            $categoryIds = Category::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
            foreach ($preview['summary']['new_categories'] as $name) {
                $categoryIds[mb_strtolower($name)] = $this->categories->create(['name' => $name])->id;
            }

            foreach ($preview['rows'] as $row) {
                $d = $row['data'];
                $data = array_filter([
                    'name' => $d['name'],
                    'category_id' => $d['category'] ? $categoryIds[mb_strtolower($d['category'])] : null,
                    'sku' => $d['sku'],
                    'barcode' => $d['barcode'],
                    'sell_price' => $d['sell'],
                    'cost_price' => $d['cost'],
                    'unit' => $d['unit'],
                    'min_stock' => $d['minStock'],
                    'track_stock' => $d['track'],
                ], fn ($v) => $v !== null);

                if ($row['action'] === 'update') {
                    // Kolom kosong = nilai lama dipertahankan; kategori kosong tetap dikosongkan sesuai file
                    $this->products->update(Product::query()->findOrFail($row['product_id']), [...$data, 'category_id' => $data['category_id'] ?? null]);
                } else {
                    $this->products->create([...$data, 'type' => 'goods', 'initial_stock' => $d['initial'] ?? 0, 'outlet_id' => $outletId]);
                }
            }

            $this->audit->log('product.imported', null, null, ['create' => $preview['summary']['create'], 'update' => $preview['summary']['update']]);
        });

        return $preview['summary'];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Angka bulat ≥ 0. Menerima 15500, "15.500", "Rp 15.500". */
    private function integer(mixed $value, string $label, array &$errors, bool $required = false): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            if ($required) {
                $errors[] = "{$label} wajib diisi.";
            }

            return null;
        }
        if (is_int($value) || (is_float($value) && floor($value) === $value)) {
            $number = (int) $value;
        } else {
            $clean = preg_replace('/^rp\s*/i', '', trim((string) $value));
            if (! preg_match('/^\d{1,3}(\.\d{3})+$|^\d+$/', $clean)) {
                $errors[] = "{$label} harus angka bulat tanpa desimal, mis. 15500.";

                return null;
            }
            $number = (int) str_replace('.', '', $clean);
        }
        if ($number < 0 || $number > 999_999_999_999) {
            $errors[] = "{$label} harus antara 0 dan 999.999.999.999.";

            return null;
        }

        return $number;
    }

    private function boolean(mixed $value, array &$errors): ?bool
    {
        $v = mb_strtolower(trim((string) $value));

        return match (true) {
            $v === '' => null,
            in_array($v, ['ya', 'y', 'yes', '1', 'true'], true) => true,
            in_array($v, ['tidak', 't', 'no', 'n', '0', 'false'], true) => false,
            default => tap(null, function () use (&$errors) {
                $errors[] = 'Lacak stok harus "ya" atau "tidak".';
            }),
        };
    }
}
