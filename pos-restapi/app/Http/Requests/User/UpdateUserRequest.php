<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends UserRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->target());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->target()->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'], // kosong = tidak diubah
            'pin' => ['sometimes', 'nullable', 'string', 'digits:6'],               // kosong = tidak diubah
            'role' => ['sometimes', 'required', ...$this->roleRule()],
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
            fn (Validator $v) => $this->preventSelfLockout($v),
            fn (Validator $v) => $this->requirePinForCashier($v),
        ];
    }

    public function target(): User
    {
        return $this->route('user');
    }

    protected function shouldCheckOutlets(): bool
    {
        return $this->has('outlet_ids');
    }

    /** Mencegah user mengunci dirinya sendiri keluar dari sistem. */
    private function preventSelfLockout(Validator $v): void
    {
        if (! $this->user()->is($this->target())) {
            return;
        }

        if ($this->has('role') && $this->input('role') !== $this->target()->role->value) {
            $v->errors()->add('role', 'Anda tidak bisa mengubah peran akun sendiri.');
        }

        if ($this->has('is_active') && ! $this->boolean('is_active')) {
            $v->errors()->add('is_active', 'Anda tidak bisa menonaktifkan akun sendiri.');
        }
    }

    private function requirePinForCashier(Validator $v): void
    {
        $becomesCashier = $this->resultingRole() === Role::Cashier;

        if ($becomesCashier && ! $this->target()->pin && ! $this->filled('pin')) {
            $v->errors()->add('pin', 'PIN wajib diisi untuk kasir agar bisa login di perangkat kasir.');
        }
    }
}
