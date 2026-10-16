<?php

namespace App\Http\Requests\Shift;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;

class CurrentShiftRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return true; // hanya membaca shift milik user sendiri
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
