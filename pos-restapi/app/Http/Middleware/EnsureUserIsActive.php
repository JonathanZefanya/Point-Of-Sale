<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User yang dinonaktifkan di tengah sesi langsung kehilangan akses,
 * walaupun tokennya masih tersimpan di perangkat.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->currentAccessToken()?->delete();
            throw new BusinessException('Akun Anda dinonaktifkan. Hubungi pemilik toko untuk mengaktifkan kembali.', 403);
        }

        return $next($request);
    }
}
