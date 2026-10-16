<?php

namespace App\Http\Requests\Sale;

use App\Enums\SaleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hasil dibatasi Sale::visibleTo()
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(SaleStatus::class)->except([SaleStatus::Held])],
            'search' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['completed_at', '-completed_at', 'grand_total', '-grand_total'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['from' => 'tanggal mulai', 'to' => 'tanggal akhir'];
    }
}
