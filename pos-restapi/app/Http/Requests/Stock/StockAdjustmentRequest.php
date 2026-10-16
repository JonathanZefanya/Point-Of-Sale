<?php

namespace App\Http\Requests\Stock;

use App\Enums\AdjustmentReason;
use App\Http\Requests\Concerns\ResolvesOutlet;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Penyesuaian stok (F8): +/− jumlah dengan alasan. */
class StockAdjustmentRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->role->canManageStock();
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'variant_id' => ['nullable', 'integer'],
            'quantity_change' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:200', Rule::requiredIf(fn () => $this->input('reason') === AdjustmentReason::Other->value)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $product = Product::query()->find($this->input('product_id'));
            if ($product && (! $product->track_stock || ! $product->type->hasStock())) {
                $v->errors()->add('product_id', "{$product->name} tidak dilacak stoknya. Aktifkan \"Lacak stok\" di pengaturan produk terlebih dahulu.");
            }
            // Produk bervarian: stok per varian, jadi varian wajib dipilih dan harus milik produk ini
            $variantId = $this->input('variant_id');
            $hasVariants = $product?->variants()->exists();
            if ($product && $hasVariants && ! $product->variants()->whereKey((int) $variantId)->exists()) {
                $v->errors()->add('variant_id', "Pilih varian {$product->name} yang akan disesuaikan.");
            }
            if ($product && ! $hasVariants && $variantId) {
                $v->errors()->add('variant_id', "{$product->name} tidak memiliki varian.");
            }
        }];
    }

    public function messages(): array
    {
        return [
            'quantity_change.not_in' => 'Jumlah tidak boleh 0. Isi angka positif untuk menambah atau negatif untuk mengurangi.',
            'note.required' => 'Tulis keterangan untuk alasan "Lainnya".',
        ];
    }

    public function attributes(): array
    {
        return ['product_id' => 'produk', 'variant_id' => 'varian', 'quantity_change' => 'jumlah', 'reason' => 'alasan', 'note' => 'keterangan'];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }

    public function product(): Product
    {
        return Product::query()->findOrFail($this->validated('product_id'));
    }
}
