<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockMovementType;
use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementsRequest extends FormRequest
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
            'variant_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['from' => 'tanggal mulai', 'to' => 'tanggal akhir', 'type' => 'jenis'];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }
}
