<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;

class HeldSalesRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return true; // outlet divalidasi oleh outletRule()
    }

    public function rules(): array
    {
        return ['outlet_id' => $this->outletRule()];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }
}
