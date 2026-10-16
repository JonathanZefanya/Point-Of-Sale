<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

/** Terima barang (F9.2): jumlah diterima per item PO; 0 = belum datang. */
class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageStock()
            && $this->user()->canAccessOutlet($this->route('purchase_order')->outlet_id);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.quantity' => 'jumlah diterima'];
    }
}
