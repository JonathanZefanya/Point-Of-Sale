<?php

namespace App\Http\Requests\Stock;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Buat transfer stok (F10): dari outlet sendiri ke outlet lain, produk berstok + jumlah. */
class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageStock();
    }

    public function rules(): array
    {
        return [
            'from_outlet_id' => ['required', 'integer', function ($attr, $value, $fail) {
                if (! $this->user()->canAccessOutlet((int) $value)) {
                    $fail('Outlet asal tidak ditemukan atau bukan outlet Anda.');
                }
            }],
            'to_outlet_id' => ['required', 'integer', 'different:from_outlet_id', Rule::exists('outlets', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
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
                if (! $product->track_stock || ! $product->type->hasStock()) {
                    $v->errors()->add("items.{$i}.product_id", "{$product->name} tidak dilacak stoknya sehingga tidak bisa ditransfer.");
                }
                $variantId = (int) ($item['variant_id'] ?? 0);
                if ($product->variants->isNotEmpty() && ! $product->variants->contains('id', $variantId)) {
                    $v->errors()->add("items.{$i}.variant_id", "Pilih varian {$product->name}.");
                }
                if (isset($seen[$product->id.':'.$variantId])) {
                    $v->errors()->add("items.{$i}.product_id", "{$product->name} sudah ada di daftar. Gabungkan jumlahnya.");
                }
                $seen[$product->id.':'.$variantId] = true;
            }
        }];
    }

    public function messages(): array
    {
        return [
            'to_outlet_id.different' => 'Outlet tujuan harus berbeda dari outlet asal.',
            'to_outlet_id.required' => 'Pilih outlet tujuan.',
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.min' => 'Tambahkan minimal satu produk.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
        ];
    }
}
