<?php

namespace App\Http\Requests\Purchase;

use App\Http\Requests\Concerns\ResolvesOutlet;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Buat/ubah PO (F9.1): supplier, produk + qty + harga beli, simpan sebagai draf atau kirim (dipesan). */
class PurchaseOrderRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->role->canManageStock();
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->route('purchase_order') ? ['sometimes'] : $this->outletRule(),
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::in(['draft', 'ordered'])],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.cost_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $products = Product::query()->with('variants')->whereKey(collect($this->input('items', []))->pluck('product_id')->filter())->get()->keyBy('id');
            $seen = [];
            foreach ($this->input('items', []) as $i => $item) {
                $product = $products->get($item['product_id'] ?? 0);
                if (! $product) {
                    continue;
                }
                $variantId = (int) ($item['variant_id'] ?? 0);
                if ($product->variants->isNotEmpty() && ! $product->variants->contains('id', $variantId)) {
                    $v->errors()->add("items.{$i}.variant_id", "Pilih varian {$product->name}.");
                }
                $key = $product->id.':'.$variantId;
                if (isset($seen[$key])) {
                    $v->errors()->add("items.{$i}.product_id", "{$product->name} sudah ada di daftar. Gabungkan jumlahnya di baris yang sama.");
                }
                $seen[$key] = true;
            }
        }];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Tambahkan minimal satu produk ke PO.',
            'items.min' => 'Tambahkan minimal satu produk ke PO.',
            'items.*.quantity.min' => 'Jumlah pesan minimal 1.',
            'items.*.cost_price.required' => 'Isi harga beli.',
            'supplier_id.required' => 'Pilih supplier.',
        ];
    }

    public function attributes(): array
    {
        return ['supplier_id' => 'supplier', 'items.*.quantity' => 'jumlah', 'items.*.cost_price' => 'harga beli', 'items.*.product_id' => 'produk'];
    }
}
