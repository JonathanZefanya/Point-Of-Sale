<?php

namespace App\Services\Sales;

use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Exceptions\PinLockedException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Otorisasi aksi sensitif dengan PIN supervisor (void, refund, diskon di atas batas — fitur-pos.md §2).
 * Supervisor = Pemilik/Manajer aktif yang boleh mengakses outlet. PIN supervisor unik (divalidasi di form karyawan),
 * sehingga PIN saja cukup untuk mengenali siapa yang menyetujui.
 */
class SupervisorAuthorizer
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = 300;

    public function authorize(string $pin, int $outletId, User $requester): User
    {
        $key = "supervisor-pin:{$requester->id}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw new PinLockedException('PIN supervisor salah '.self::MAX_ATTEMPTS.' kali. Coba lagi dalam '.ceil($seconds / 60).' menit.', $seconds);
        }

        $supervisor = $this->findByPin($pin, $outletId);

        if (! $supervisor) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            throw new BusinessException('PIN supervisor salah atau bukan milik pemilik/manajer outlet ini.', 422, [
                'errors' => ['supervisor_pin' => ['PIN supervisor salah atau bukan milik pemilik/manajer outlet ini.']],
            ]);
        }

        RateLimiter::clear($key);

        return $supervisor;
    }

    private function findByPin(string $pin, int $outletId): ?User
    {
        return User::query()
            ->whereIn('role', [Role::Owner, Role::Manager])
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get()
            ->first(fn (User $u) => $u->canAccessOutlet($outletId) && Hash::check($pin, $u->pin));
    }

    /** Dipakai validasi form karyawan: apakah PIN ini sudah dipakai supervisor lain. */
    public function pinTakenBySupervisor(string $pin, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->whereIn('role', [Role::Owner, Role::Manager])
            ->whereNotNull('pin')
            ->when($exceptUserId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get()
            ->contains(fn (User $u) => Hash::check($pin, $u->pin));
    }
}
