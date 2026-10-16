<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/** Terima transfer (F10) — hanya outlet tujuan. Jumlah per item; tidak dikirim = sesuai jumlah dikirim. */
class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageStock()
            && $this->user()->canAccessOutlet($this->route('stock_transfer')->to_outlet_id);
    }

    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.quantity' => 'jumlah diterima', 'note' => 'catatan'];
    }
}
