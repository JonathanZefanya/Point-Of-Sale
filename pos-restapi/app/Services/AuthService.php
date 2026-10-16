<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\PinLockedException;
use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** Batas percobaan login email per (email + IP) per menit. */
    public const LOGIN_MAX_ATTEMPTS = 5;

    /**
     * Login back office dengan email + password.
     *
     * @return array{token: string, user: User}
     */
    public function loginWithPassword(string $email, string $password, string $ip): array
    {
        $key = 'login:'.mb_strtolower($email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::LOGIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw new BusinessException(
                "Terlalu banyak percobaan login. Coba lagi dalam {$this->humanDuration($seconds)}.",
                429,
            );
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah. Periksa kembali lalu coba lagi.',
            ]);
        }

        RateLimiter::clear($key);
        $this->ensureActive($user);

        return $this->issueToken($user, 'backoffice');
    }

    /**
     * Login kasir dengan PIN. Salah N kali (pengaturan pin.max_attempts) → dikunci
     * selama pin.lockout_minutes, dihitung sejak percobaan salah terakhir.
     *
     * @return array{token: string, user: User}
     */
    public function loginWithPin(int $userId, string $pin): array
    {
        $user = User::query()->find($userId);

        if (! $user || ! $user->pin) {
            throw ValidationException::withMessages([
                'user_id' => 'Kasir tidak ditemukan atau belum memiliki PIN. Hubungi pemilik toko.',
            ]);
        }

        $this->ensureActive($user);
        $this->ensurePinNotLocked($user);

        if (! Hash::check($pin, $user->pin)) {
            $this->registerFailedPin($user);
        }

        Cache::forget($this->pinAttemptsKey($user));

        return $this->issueToken($user, 'cashier');
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Daftar user yang bisa login PIN di sebuah outlet (layar "pilih kasir").
     *
     * @return Collection<int, User>
     */
    public function pinUsersForOutlet(string $outletCode): Collection
    {
        $outlet = Outlet::query()->where('code', $outletCode)->first();

        if (! $outlet) {
            throw new BusinessException("Outlet dengan kode {$outletCode} tidak ditemukan. Periksa pengaturan perangkat kasir.", 404);
        }

        return $outlet->users()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->orderBy('name')
            ->get(['users.id', 'users.name']);
    }

    private function ensureActive(User $user): void
    {
        if (! $user->is_active) {
            throw new BusinessException('Akun Anda dinonaktifkan. Hubungi pemilik toko untuk mengaktifkan kembali.', 403);
        }
    }

    private function ensurePinNotLocked(User $user): void
    {
        $lockedUntil = Cache::get($this->pinLockKey($user));

        if ($lockedUntil && $lockedUntil > now()->timestamp) {
            $this->throwLocked($lockedUntil - now()->timestamp);
        }
    }

    private function registerFailedPin(User $user): never
    {
        $max = (int) Setting::get('pin.max_attempts', 5);
        $lockMinutes = (int) Setting::get('pin.lockout_minutes', 5);

        $attempts = (int) Cache::get($this->pinAttemptsKey($user), 0) + 1;

        if ($attempts >= $max) {
            Cache::forget($this->pinAttemptsKey($user));
            Cache::put($this->pinLockKey($user), now()->addMinutes($lockMinutes)->timestamp, now()->addMinutes($lockMinutes));
            $this->throwLocked($lockMinutes * 60, "PIN salah {$max} kali. ");
        }

        Cache::put($this->pinAttemptsKey($user), $attempts, now()->addMinutes($lockMinutes));
        $left = $max - $attempts;

        throw ValidationException::withMessages([
            'pin' => "PIN salah. Sisa {$left} percobaan sebelum dikunci {$lockMinutes} menit.",
        ]);
    }

    private function throwLocked(int $seconds, string $prefix = ''): never
    {
        throw new PinLockedException(
            $prefix."Login PIN dikunci. Coba lagi dalam {$this->humanDuration($seconds)} atau minta supervisor login.",
            $seconds,
        );
    }

    /** @return array{token: string, user: User} */
    private function issueToken(User $user, string $device): array
    {
        $token = $user->createToken($device)->plainTextToken;

        return ['token' => $token, 'user' => $user->load('outlets')];
    }

    private function pinAttemptsKey(User $user): string
    {
        return "pin-attempts:{$user->id}";
    }

    private function pinLockKey(User $user): string
    {
        return "pin-locked-until:{$user->id}";
    }

    private function humanDuration(int $seconds): string
    {
        $seconds = max(1, $seconds);
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        if ($minutes === 0) {
            return "{$rest} detik";
        }

        return $rest === 0 ? "{$minutes} menit" : "{$minutes} menit {$rest} detik";
    }
}
