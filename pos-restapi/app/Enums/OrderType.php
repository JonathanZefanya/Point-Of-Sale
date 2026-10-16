<?php

namespace App\Enums;

/** Tipe pesanan (3.3). */
enum OrderType: string
{
    case DineIn = 'dine_in';
    case TakeAway = 'take_away';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Makan di tempat',
            self::TakeAway => 'Bawa pulang',
            self::Delivery => 'Delivery',
        };
    }
}
