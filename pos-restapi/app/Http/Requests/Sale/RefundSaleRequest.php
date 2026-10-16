<?php

namespace App\Http\Requests\Sale;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('sale')); // aturan sama: kasir/manajer/pemilik outlet itu
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.restock' => ['sometimes', 'boolean'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'supervisor_pin' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih item yang dikembalikan.',
            'items.min' => 'Pilih item yang dikembalikan.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
            'reason.required' => 'Tulis alasan refund, mis. "Barang cacat".',
            'supervisor_pin.required' => 'Minta pemilik/manajer memasukkan PIN untuk menyetujui refund.',
            'supervisor_pin.digits' => 'PIN supervisor harus 6 digit angka.',
        ];
    }

    public function attributes(): array
    {
        return ['method' => 'metode pengembalian', 'reason' => 'alasan', 'supervisor_pin' => 'PIN supervisor'];
    }
}
