<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Ordered => 'Dipesan',
            self::Partial => 'Diterima sebagian',
            self::Received => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Isi PO masih boleh diubah (belum ada barang diterima). */
    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }

    public function receivable(): bool
    {
        return in_array($this, [self::Draft, self::Ordered, self::Partial], true);
    }
}
