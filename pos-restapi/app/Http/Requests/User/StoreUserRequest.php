<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends UserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'pin' => ['nullable', 'string', 'digits:6', Rule::requiredIf(fn () => $this->input('role') === Role::Cashier->value)],
            'role' => ['required', ...$this->roleRule()],
            'is_active' => ['sometimes', 'boolean'],
            'commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'], // komisi penjualan (3.7)
            ...$this->outletRules(),
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $v) => $this->requireOutletsUnlessOwner($v),
            fn (Validator $v) => $this->requireUniqueSupervisorPin($v),
        ];
    }

    protected function shouldCheckOutlets(): bool
    {
        return true;
    }
}
