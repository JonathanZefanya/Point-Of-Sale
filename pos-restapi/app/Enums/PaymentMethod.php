<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Debit = 'debit';
    case Credit = 'credit';
    case EWallet = 'ewallet';
    case Transfer = 'transfer';
    case Points = 'points'; // tukar poin loyalty (2.4), wajib ada pelanggan

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS',
            self::Debit => 'Debit',
            self::Credit => 'Kredit',
            self::EWallet => 'E-Wallet',
            self::Transfer => 'Transfer',
            self::Points => 'Poin',
        };
    }

    /** Hanya tunai yang menghasilkan kembalian dan masuk hitungan kas fisik shift. */
    public function isCash(): bool
    {
        return $this === self::Cash;
    }
}
