<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('sale'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'supervisor_pin' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Tulis alasan pembatalan, mis. "Pelanggan batal beli".',
            'supervisor_pin.required' => 'Minta pemilik/manajer memasukkan PIN untuk menyetujui pembatalan.',
            'supervisor_pin.digits' => 'PIN supervisor harus 6 digit angka.',
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'alasan', 'supervisor_pin' => 'PIN supervisor'];
    }
}
