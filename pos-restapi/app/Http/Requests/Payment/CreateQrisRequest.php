<?php

namespace App\Http\Requests\Payment;

use App\Enums\Role;
use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;

/** Buat QRIS dinamis untuk nominal tertentu (3.5). */
class CreateQrisRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return in_array($this->user()->role, [Role::Owner, Role::Manager, Role::Cashier], true);
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'nominal'];
    }
}
