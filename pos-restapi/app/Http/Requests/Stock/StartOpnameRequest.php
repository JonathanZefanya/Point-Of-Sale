<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mulai stock opname (F8): outlet + kategori (kosong = semua produk). */
class StartOpnameRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->role->canManageStock();
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
