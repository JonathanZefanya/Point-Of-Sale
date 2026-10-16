<?php

namespace App\Http\Requests\Sale;

use App\Enums\OrderType;
use App\Enums\Role;
use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /sales/hold — simpan keranjang untuk dilanjutkan nanti (F4). */
class HoldSaleRequest extends FormRequest
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
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:30'],
            'items.*.modifier_ids.*' => ['integer'],
            'hold_label' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:500'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'held_sale_id' => ['nullable', 'integer'],
            // F&B (3.3): bill meja & tipe pesanan
            'table_id' => ['nullable', 'integer'],
            'order_type' => ['nullable', Rule::enum(OrderType::class)],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Keranjang masih kosong. Tidak ada yang bisa disimpan.', 'items.min' => 'Keranjang masih kosong. Tidak ada yang bisa disimpan.'];
    }

    public function attributes(): array
    {
        return ['hold_label' => 'label'];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }
}
