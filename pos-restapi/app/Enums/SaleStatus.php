<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Held = 'held';
    case Completed = 'completed';
    case Void = 'void';
    case Refunded = 'refunded';
    case PartialRefund = 'partial_refund';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Tersimpan',
            self::Completed => 'Selesai',
            self::Void => 'Dibatalkan',
            self::Refunded => 'Dikembalikan',
            self::PartialRefund => 'Dikembalikan Sebagian',
        };
    }

    /** Dihitung sebagai omzet di laporan. */
    public function countsAsRevenue(): bool
    {
        return in_array($this, [self::Completed, self::PartialRefund], true);
    }
}
