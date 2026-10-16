<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\User;

/** Transaksi dibatasi ke outlet yang boleh diakses user (CLAUDE.md §4). */
class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        return $user->canAccessOutlet($sale->outlet_id);
    }

    /** Mengajukan void; persetujuan tetap lewat PIN supervisor. */
    public function void(User $user, Sale $sale): bool
    {
        return in_array($user->role, [Role::Owner, Role::Manager, Role::Cashier], true)
            && $user->canAccessOutlet($sale->outlet_id);
    }
}
