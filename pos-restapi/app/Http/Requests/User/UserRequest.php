<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use App\Models\User;
use App\Services\Sales\SupervisorAuthorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan bersama untuk tambah & ubah karyawan. Role dan outlet yang boleh dipilih
 * dibatasi oleh role pengguna yang sedang login.
 */
abstract class UserRequest extends FormRequest
{
    protected function actor(): User
    {
        return $this->user();
    }

    protected function roleRule(): array
    {
        return [Rule::enum(Role::class)->only($this->actor()->role->assignableRoles())];
    }

    protected function outletRules(): array
    {
        $exists = Rule::exists('outlets', 'id')->whereNull('deleted_at');

        if (! $this->actor()->role->seesAllOutlets()) {
            $exists->whereIn('id', $this->actor()->outletIds() ?: [0]);
        }

        return [
            'outlet_ids' => ['array'],
            'outlet_ids.*' => ['integer', 'distinct', $exists],
        ];
    }

    public function messages(): array
    {
        return [
            'role.enum' => 'Peran ini tidak bisa Anda berikan. Pilih Kasir atau Staf Gudang, atau minta pemilik toko.',
            'outlet_ids.required' => 'Pilih minimal satu outlet tempat karyawan bekerja.',
            'outlet_ids.min' => 'Pilih minimal satu outlet tempat karyawan bekerja.',
            'outlet_ids.*.exists' => 'Outlet yang dipilih tidak ditemukan atau bukan outlet Anda.',
            'pin.required' => 'PIN wajib diisi untuk kasir agar bisa login di perangkat kasir.',
            'pin.digits' => 'PIN harus 6 digit angka.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'outlet_ids' => 'outlet',
            'is_active' => 'status aktif',
        ];
    }

    /** Role final setelah request ini diterapkan (untuk aturan yang bergantung pada role). */
    protected function resultingRole(): ?Role
    {
        return Role::tryFrom((string) $this->input('role')) ?? $this->route('user')?->role;
    }

    /** Non-owner wajib punya minimal satu outlet. */
    protected function requireOutletsUnlessOwner(Validator $validator): void
    {
        if ($this->resultingRole() === Role::Owner || ! $this->shouldCheckOutlets()) {
            return;
        }

        if (count((array) $this->input('outlet_ids', [])) === 0) {
            $validator->errors()->add('outlet_ids', 'Pilih minimal satu outlet tempat karyawan bekerja.');
        }
    }

    /**
     * PIN Pemilik/Manajer harus unik: PIN supervisor dipakai menyetujui void/refund tanpa memilih nama,
     * jadi satu PIN harus menunjuk satu orang.
     */
    protected function requireUniqueSupervisorPin(Validator $validator): void
    {
        $role = $this->resultingRole();
        if (! $this->filled('pin') || ! $role?->canAuthorize()) {
            return;
        }

        $exceptId = $this->route('user')?->id;
        if (app(SupervisorAuthorizer::class)->pinTakenBySupervisor((string) $this->input('pin'), $exceptId)) {
            $validator->errors()->add('pin', 'PIN ini sudah dipakai pemilik/manajer lain. Pilih PIN lain.');
        }
    }

    abstract protected function shouldCheckOutlets(): bool;
}
