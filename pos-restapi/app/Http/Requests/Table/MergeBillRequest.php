<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

/** Gabung bill ke bill meja lain (3.3). */
class MergeBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('sale'));
    }

    public function rules(): array
    {
        return ['target_sale_id' => ['required', 'integer']];
    }

    public function messages(): array
    {
        return ['target_sale_id.required' => 'Pilih bill tujuan penggabungan.'];
    }
}
