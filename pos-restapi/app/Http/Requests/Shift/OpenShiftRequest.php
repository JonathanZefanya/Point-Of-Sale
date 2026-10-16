<?php

namespace App\Http\Requests\Shift;

use App\Http\Requests\Concerns\ResolvesOutlet;
use App\Models\Shift;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class OpenShiftRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): Response
    {
        return Gate::inspect('open', [Shift::class, (int) $this->input('outlet_id')]);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'opening_cash' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function attributes(): array
    {
        return ['opening_cash' => 'modal awal'];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }
}
