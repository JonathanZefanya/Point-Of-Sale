<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tambah (tanpa route {product}) & ubah produk. Mendukung multipart untuk gambar;
 * ubah dengan file dikirim sebagai POST + _method=PUT.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->product();

        return $product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        $required = $this->product() ? 'sometimes' : 'required';
        $ignoreId = $this->product()?->id;

        return [
            'name' => [$required, 'string', 'max:150'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
            // SKU unik termasuk produk terhapus: tetap dipakai di riwayat transaksi
            'sku' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($ignoreId)],
            'barcode' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('products', 'barcode')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'type' => ['sometimes', Rule::enum(ProductType::class)->only(ProductType::available())],
            'unit' => ['sometimes', 'string', 'max:20'],
            'sell_price' => [$required, 'integer', 'min:0', 'max:999999999999'],
            'cost_price' => ['sometimes', 'integer', 'min:0', 'max:999999999999'],
            'track_stock' => ['sometimes', 'boolean'],
            'min_stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            ...$this->initialStockRules(),
            ...$this->variantRules(),
            'modifier_group_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            'modifier_group_ids.*' => ['integer', 'distinct', Rule::exists('modifier_groups', 'id')],
            // Harga & ketersediaan per outlet (2.8); sell_price kosong = harga default
            'outlet_prices' => ['sometimes', 'nullable', 'array', 'max:200'],
            'outlet_prices.*.outlet_id' => ['required', 'integer', 'distinct', Rule::exists('outlets', 'id')->whereNull('deleted_at')],
            'outlet_prices.*.sell_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'outlet_prices.*.is_available' => ['required', 'boolean'],
        ];
    }

    /**
     * Varian (2.1): disinkronkan — baris dengan id diubah, tanpa id ditambah, yang tidak dikirim dihapus.
     * Mengirim `variants: []` menghapus semua varian.
     */
    private function variantRules(): array
    {
        return [
            'variants' => ['sometimes', 'nullable', 'array', 'max:50'], // null (FormData kosong) = hapus semua varian
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'variants.*.sku' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:50', 'distinct'],
            'variants.*.sell_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'variants.*.cost_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $v) => $this->checkVariantUniqueness($v)];
    }

    /** SKU & barcode varian unik terhadap varian/produk lain (SKU termasuk yang terhapus). */
    private function checkVariantUniqueness(Validator $v): void
    {
        $productId = $this->product()?->id;
        foreach ((array) $this->input('variants', []) as $i => $variant) {
            $ignore = $variant['id'] ?? null;
            $sku = $variant['sku'] ?? null;
            if ($sku && (ProductVariant::withTrashed()->where('sku', $sku)->whereKeyNot($ignore ?? 0)->exists()
                || Product::withTrashed()->where('sku', $sku)->exists())) {
                $v->errors()->add("variants.{$i}.sku", "SKU {$sku} sudah dipakai. Kosongkan agar dibuat otomatis.");
            }
            $barcode = $variant['barcode'] ?? null;
            if ($barcode && (ProductVariant::query()->where('barcode', $barcode)->whereKeyNot($ignore ?? 0)->exists()
                || Product::query()->where('barcode', $barcode)->whereKeyNot($productId ?? 0)->exists())) {
                $v->errors()->add("variants.{$i}.barcode", "Barcode {$barcode} sudah dipakai produk lain.");
            }
            if ($ignore && $productId && ! ProductVariant::query()->whereKey($ignore)->where('product_id', $productId)->exists()) {
                $v->errors()->add("variants.{$i}.id", 'Varian ini bukan milik produk ini. Muat ulang halaman.');
            }
        }
    }

    /**
     * Stok awal hanya saat tambah produk (F7). Perubahan stok setelahnya lewat penyesuaian.
     * Outlet otomatis bila user hanya punya satu outlet.
     */
    private function initialStockRules(): array
    {
        $needsOutlet = fn () => $this->wantsInitialStock();

        return [
            'initial_stock' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'outlet_id' => [
                Rule::requiredIf($needsOutlet),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && ! in_array((int) $value, $this->user()->accessibleOutletIds(), true)) {
                        $fail('Outlet untuk stok awal tidak ditemukan atau bukan outlet Anda.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'SKU sudah dipakai produk lain. Kosongkan agar dibuat otomatis.',
            'sku.regex' => 'SKU hanya boleh berisi huruf, angka, titik, strip, dan garis bawah.',
            'barcode.unique' => 'Barcode sudah dipakai produk lain.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.mimes' => 'Gambar harus berformat JPG, PNG, atau WEBP.',
            'outlet_id.required' => 'Pilih outlet tempat stok awal disimpan.',
            'variants.*.name.required' => 'Nama varian wajib diisi, mis. Regular atau Large.',
            'variants.*.name.distinct' => 'Nama varian tidak boleh sama.',
            'variants.*.sell_price.required' => 'Harga jual varian wajib diisi.',
            'variants.*.sku.distinct' => 'SKU varian tidak boleh sama.',
            'variants.*.barcode.distinct' => 'Barcode varian tidak boleh sama.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'type' => 'jenis produk',
            'unit' => 'satuan',
            'min_stock' => 'stok minimum',
            'initial_stock' => 'stok awal',
            'image' => 'gambar',
            'is_active' => 'status aktif',
        ];
    }

    /** Ada stok awal yang akan dicatat: produk baru, atau varian baru (tanpa id). */
    private function wantsInitialStock(): bool
    {
        if (! $this->product() && (int) $this->input('initial_stock') > 0) {
            return true;
        }

        return collect((array) $this->input('variants', []))
            ->contains(fn ($v) => empty($v['id']) && (int) ($v['initial_stock'] ?? 0) > 0);
    }

    public function product(): ?Product
    {
        return $this->route('product');
    }

    protected function prepareForValidation(): void
    {
        // Stok awal tanpa outlet → pakai satu-satunya outlet user
        if ($this->wantsInitialStock() && blank($this->input('outlet_id'))) {
            $outlets = $this->user()->accessibleOutletIds();
            if (count($outlets) === 1) {
                $this->merge(['outlet_id' => $outlets[0]]);
            }
        }

        // Kolom teks kosong dari form multipart dianggap null
        foreach (['sku', 'barcode', 'category_id'] as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }
        // FormData mengirim boolean sebagai teks "true"/"false"
        foreach (['track_stock', 'is_active', 'remove_image'] as $field) {
            if (in_array($this->input($field), ['true', 'false'], true)) {
                $this->merge([$field => $this->input($field) === 'true']);
            }
        }
        foreach (['name', 'sku', 'barcode', 'unit'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }
}
