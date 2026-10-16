<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Shift dikelola kasir pemiliknya. Pemilik/Manajer outlet yang sama juga boleh
 * (mis. menutup shift kasir yang lupa ditutup).
 */
class ShiftPolicy
{
    public function open(User $user, int $outletId): Response
    {
        if (! in_array($user->role, [Role::Owner, Role::Manager, Role::Cashier], true)) {
            return Response::deny('Akun Anda tidak bisa membuka shift kasir.');
        }

        return $user->canAccessOutlet($outletId)
            ? Response::allow()
            : Response::deny('Anda tidak ditugaskan di outlet ini.');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $this->manage($user, $shift)->allowed();
    }

    public function manage(User $user, Shift $shift): Response
    {
        if ($shift->user_id === $user->id) {
            return Response::allow();
        }

        return $user->role->canAuthorize() && $user->canAccessOutlet($shift->outlet_id)
            ? Response::allow()
            : Response::deny('Shift ini milik kasir lain.');
    }
}
