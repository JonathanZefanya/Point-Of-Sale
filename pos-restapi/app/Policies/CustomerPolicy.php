<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

/** Kasir boleh mencari & menambah pelanggan dari layar kasir; ubah/hapus hanya Pemilik/Manajer. */
class CustomerPolicy
{
    private function sells(User $user): bool
    {
        return in_array($user->role, [Role::Owner, Role::Manager, Role::Cashier], true);
    }

    public function viewAny(User $user): bool
    {
        return $this->sells($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->sells($user);
    }

    public function create(User $user): bool
    {
        return $this->sells($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->role->canManageCatalog();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->role->canManageCatalog();
    }
}
