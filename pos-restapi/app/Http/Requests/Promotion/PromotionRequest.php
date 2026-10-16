<?php

namespace App\Http\Requests\Promotion;

use App\Enums\PromotionType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah/ubah promo (2.5). Satu kode voucher opsional per promo (voucher_code + voucher_quota). */
class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class); // Pemilik & Manajer, sama dengan katalog
    }

    public function rules(): array
    {
        $type = PromotionType::tryFrom((string) $this->input('type'));
        $promotion = $this->route('promotion');

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'value' => [$type === PromotionType::BuyXGetY ? 'nullable' : 'required', 'integer', 'min:1', $type?->isPercent() ? 'max:100' : 'max:999999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', Rule::exists('outlets', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'requires_voucher' => ['sometimes', 'boolean'],
            'rules' => ['nullable', 'array'],
            'rules.product_ids' => ['nullable', 'array'],
            'rules.product_ids.*' => ['integer'],
            'rules.category_ids' => ['nullable', 'array'],
            'rules.category_ids.*' => ['integer'],
            'rules.min_purchase' => ['nullable', 'integer', 'min:0'],
            'rules.max_discount' => ['nullable', 'integer', 'min:1'],
            'rules.buy_product_id' => [$type === PromotionType::BuyXGetY ? 'required' : 'nullable', 'integer', Rule::exists('products', 'id')],
            'rules.buy_quantity' => [$type === PromotionType::BuyXGetY ? 'required' : 'nullable', 'integer', 'min:1'],
            'rules.get_product_id' => [$type === PromotionType::BuyXGetY ? 'required' : 'nullable', 'integer', Rule::exists('products', 'id')],
            'rules.get_quantity' => [$type === PromotionType::BuyXGetY ? 'required' : 'nullable', 'integer', 'min:1'],
            'rules.days' => ['nullable', 'array'],
            'rules.days.*' => ['integer', 'between:1,7'],
            'rules.start_time' => ['nullable', 'date_format:H:i'],
            'rules.end_time' => ['nullable', 'date_format:H:i', 'after:rules.start_time'],
            'voucher_code' => [
                Rule::requiredIf(fn () => $this->boolean('requires_voucher')), 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('vouchers', 'code')->when($promotion, fn ($rule) => $rule->where(fn ($q) => $q->where('promotion_id', '!=', $promotion->id))),
            ],
            'voucher_quota' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.max' => 'Diskon persen maksimal 100.',
            'ends_at.after' => 'Tanggal selesai harus setelah tanggal mulai.',
            'rules.end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'voucher_code.required' => 'Isi kode voucher untuk promo yang hanya berlaku dengan voucher.',
            'voucher_code.unique' => 'Kode voucher ini sudah dipakai promo lain.',
            'voucher_code.regex' => 'Kode voucher hanya boleh huruf, angka, dan strip.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama promo', 'type' => 'jenis promo', 'value' => 'nilai diskon',
            'rules.buy_product_id' => 'produk yang dibeli', 'rules.buy_quantity' => 'jumlah beli',
            'rules.get_product_id' => 'produk gratis', 'rules.get_quantity' => 'jumlah gratis',
            'rules.min_purchase' => 'minimum belanja', 'rules.max_discount' => 'maksimal diskon',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('voucher_code'))) {
            $this->merge(['voucher_code' => strtoupper(trim($this->input('voucher_code'))) ?: null]);
        }
    }
}
