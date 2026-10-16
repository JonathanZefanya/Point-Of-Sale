<?php

namespace App\Policies;

use App\Models\User;

/**
 * Dipakai untuk Product & Category: semua user login boleh melihat (kasir butuh
 * untuk layar penjualan), hanya Pemilik & Manajer yang boleh mengubah.
 */
class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canManageCatalog();
    }

    public function update(User $user): bool
    {
        return $user->role->canManageCatalog();
    }

    public function delete(User $user): bool
    {
        return $user->role->canManageCatalog();
    }
}
