<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

/** Pindah bill ke meja lain (3.3). */
class MoveBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('sale'));
    }

    public function rules(): array
    {
        return ['table_id' => ['required', 'integer']];
    }

    public function messages(): array
    {
        return ['table_id.required' => 'Pilih meja tujuan.'];
    }
}
