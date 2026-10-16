<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis yang pesannya aman ditampilkan ke user,
 * mis. "Stok Kopi Susu tidak cukup. Tersisa 2." (409).
 */
class BusinessException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra  field tambahan di body JSON, di samping "message"
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message,
        public readonly int $status = 409,
        public readonly array $extra = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
