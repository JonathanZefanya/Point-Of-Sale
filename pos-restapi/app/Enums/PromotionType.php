<?php

namespace App\Enums;

enum PromotionType: string
{
    case ItemPercent = 'item_percent';   // diskon % per item (produk/kategori tertentu)
    case ItemAmount = 'item_amount';     // potongan Rp per unit item
    case OrderPercent = 'order_percent'; // diskon % transaksi (minimum belanja, batas maksimal)
    case OrderAmount = 'order_amount';   // potongan Rp transaksi (minimum belanja)
    case BuyXGetY = 'buy_x_get_y';       // beli X gratis Y

    public function label(): string
    {
        return match ($this) {
            self::ItemPercent => 'Diskon % per item',
            self::ItemAmount => 'Potongan Rp per item',
            self::OrderPercent => 'Diskon % transaksi',
            self::OrderAmount => 'Potongan Rp transaksi',
            self::BuyXGetY => 'Beli X gratis Y',
        };
    }

    public function isItemLevel(): bool
    {
        return in_array($this, [self::ItemPercent, self::ItemAmount], true);
    }

    public function isOrderLevel(): bool
    {
        return in_array($this, [self::OrderPercent, self::OrderAmount], true);
    }

    public function isPercent(): bool
    {
        return in_array($this, [self::ItemPercent, self::OrderPercent], true);
    }
}
