<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->role->canManageStock();
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['low', 'out'])], // low = menipis (termasuk habis), out = habis
            'sort' => ['nullable', Rule::in(['name', '-name', 'quantity', '-quantity'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }
}
