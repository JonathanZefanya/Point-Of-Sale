<?php

namespace App\Enums;

enum ProductType: string
{
    case Goods = 'goods';
    case Service = 'service';
    case Composite = 'composite'; // resep/bahan baku — Fase 2

    public function label(): string
    {
        return match ($this) {
            self::Goods => 'Barang',
            self::Service => 'Jasa',
            self::Composite => 'Komposit',
        };
    }

    /** Jasa tidak punya stok. */
    public function hasStock(): bool
    {
        return $this !== self::Service;
    }

    /** @return list<self> tipe yang sudah didukung di MVP */
    public static function available(): array
    {
        return [self::Goods, self::Service];
    }
}
