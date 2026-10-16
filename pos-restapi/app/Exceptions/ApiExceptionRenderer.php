<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Mengubah semua exception di /api menjadi format JSON sesuai CLAUDE.md §4:
 * { "message": "...", "errors": { ... } }
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => response()->json([
                'message' => 'Data tidak valid.',
                'errors' => $e->errors(),
            ], 422),

            $e instanceof BusinessException => response()->json(
                ['message' => $e->getMessage(), ...$e->extra],
                $e->status,
                $e->headers,
            ),

            $e instanceof AuthenticationException => self::message('Sesi Anda telah berakhir. Silakan login kembali.', 401),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => self::message(self::forbiddenMessage($e), 403),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => self::message('Data tidak ditemukan. Mungkin sudah dihapus atau alamatnya salah.', 404),

            $e instanceof MethodNotAllowedHttpException => self::message('Metode request tidak didukung untuk alamat ini.', 405),

            $e instanceof ThrottleRequestsException => self::message(
                'Terlalu banyak percobaan. Coba lagi dalam '.($e->getHeaders()['Retry-After'] ?? 60).' detik.',
                429,
                $e->getHeaders(),
            ),

            $e instanceof HttpExceptionInterface => self::message(
                $e->getMessage() ?: 'Permintaan tidak dapat diproses.',
                $e->getStatusCode(),
                $e->getHeaders(),
            ),

            default => self::serverError($e),
        };
    }

    private static function message(string $message, int $status, array $headers = []): JsonResponse
    {
        return response()->json(['message' => $message], $status, $headers);
    }

    /** Pakai pesan dari Policy (Response::deny('...')) jika ada; selain itu pesan umum. */
    private static function forbiddenMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        return $message === '' || $message === 'This action is unauthorized.'
            ? 'Anda tidak memiliki akses untuk melakukan aksi ini. Hubungi pemilik atau manajer.'
            : $message;
    }

    private static function serverError(Throwable $e): JsonResponse
    {
        $body = ['message' => 'Terjadi kesalahan di server. Coba lagi; jika tetap gagal, hubungi admin.'];

        if (config('app.debug')) {
            $body['debug'] = [
                'exception' => $e::class,
                'detail' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ];
        }

        return response()->json($body, 500);
    }
}
