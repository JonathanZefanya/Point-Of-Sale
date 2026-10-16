<?php

namespace App\Http\Requests\Outlet;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah/ubah outlet (2.8). Hanya pemilik. Kode outlet dipakai di nomor transaksi. */
class OutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === Role::Owner;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $outlet = $this->route('outlet');

        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('outlets', 'code')->ignore($outlet?->id)],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'service_charge_rate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode outlet hanya huruf besar dan angka tanpa spasi, mis. JKT02.',
            'code.unique' => 'Kode outlet ini sudah dipakai outlet lain (termasuk yang sudah dihapus).',
            'phone.regex' => 'Nomor telepon hanya boleh angka, spasi, +, -, dan tanda kurung.',
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'kode outlet', 'name' => 'nama outlet', 'tax_rate' => 'tarif pajak', 'service_charge_rate' => 'service charge'];
    }
}
