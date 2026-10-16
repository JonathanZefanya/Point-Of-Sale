<?php

namespace App\Http\Requests\Sale;

use App\Enums\Role;
use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;

/** POST /sales/quote — hitung total keranjang (promo, voucher, diskon manual, pajak) tanpa menyimpan. */
class QuoteSaleRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return in_array($this->user()->role, [Role::Owner, Role::Manager, Role::Cashier], true);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:30'],
            'items.*.modifier_ids.*' => ['integer'],
            ...StoreSaleRequest::discountRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }
}
