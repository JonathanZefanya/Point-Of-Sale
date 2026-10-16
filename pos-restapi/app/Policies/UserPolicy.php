<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Owner: semua user. Manager: hanya Kasir & Staf Gudang di outlet yang sama.
 * Kasir & Staf Gudang: tidak punya akses manajemen karyawan.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageUsers();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target)
            || ($actor->role->canManageUsers() && $this->sharesOutlet($actor, $target));
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageUsers();
    }

    public function update(User $actor, User $target): Response
    {
        if ($actor->is($target) && $actor->role->canManageUsers()) {
            return Response::allow();
        }

        return $this->manages($actor, $target);
    }

    public function delete(User $actor, User $target): Response
    {
        if ($actor->is($target)) {
            return Response::deny('Anda tidak bisa menghapus akun sendiri. Minta pemilik lain untuk melakukannya.');
        }

        return $this->manages($actor, $target);
    }

    private function manages(User $actor, User $target): Response
    {
        if (! $actor->role->canManageUsers()) {
            return Response::deny();
        }

        if (! in_array($target->role, $actor->role->assignableRoles(), true)) {
            return Response::deny("Anda tidak bisa mengubah akun {$target->role->label()}. Hubungi pemilik toko.");
        }

        return $this->sharesOutlet($actor, $target)
            ? Response::allow()
            : Response::deny('Karyawan ini tidak ditugaskan di outlet Anda.');
    }

    private function sharesOutlet(User $actor, User $target): bool
    {
        if ($actor->role->seesAllOutlets()) {
            return true;
        }

        return count(array_intersect($actor->outletIds(), $target->outletIds())) > 0;
    }
}
