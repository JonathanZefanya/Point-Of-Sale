<?php

namespace App\Enums;

/** Alasan penyesuaian stok (F8). */
enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Expired = 'expired';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Rusak',
            self::Lost => 'Hilang',
            self::Expired => 'Kadaluarsa',
            self::Other => 'Lainnya',
        };
    }
}
