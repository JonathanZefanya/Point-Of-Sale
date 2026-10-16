<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/** Simpan jumlah fisik ke draf opname. counted_quantity null = kosongkan (tidak dihitung). */
class SaveOpnameCountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageStock()
            && $this->user()->canAccessOutlet($this->route('stock_opname')->outlet_id);
    }

    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:5000'],
            'items.*.id' => ['required', 'integer'],
            'items.*.counted_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['items.*.counted_quantity.min' => 'Jumlah fisik tidak boleh negatif.'];
    }
}
