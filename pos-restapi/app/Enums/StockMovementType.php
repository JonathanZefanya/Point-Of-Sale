<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Sale = 'sale';
    case SaleVoid = 'sale_void';
    case Refund = 'refund';
    case Purchase = 'purchase';
    case Adjustment = 'adjustment';
    case Opname = 'opname';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stok Awal',
            self::Sale => 'Penjualan',
            self::SaleVoid => 'Pembatalan Penjualan',
            self::Refund => 'Pengembalian',
            self::Purchase => 'Pembelian',
            self::Adjustment => 'Penyesuaian',
            self::Opname => 'Stock Opname',
            self::TransferOut => 'Transfer Keluar',
            self::TransferIn => 'Transfer Masuk',
        };
    }
}
