<?php

namespace App\Enums;

enum CustomerGroup: string
{
    case Member = 'member';
    case Reseller = 'reseller';
    case Vip = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Reseller => 'Reseller',
            self::Vip => 'VIP',
        };
    }
}
