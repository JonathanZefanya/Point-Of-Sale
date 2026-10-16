<?php

namespace App\Exceptions;

/**
 * Login PIN sedang dikunci. Body berisi `retry_after` (detik) agar layar kasir
 * bisa menampilkan hitung mundur.
 */
class PinLockedException extends BusinessException
{
    public function __construct(string $message, int $retryAfter)
    {
        parent::__construct($message, 429, ['retry_after' => $retryAfter], ['Retry-After' => (string) $retryAfter]);
    }
}
