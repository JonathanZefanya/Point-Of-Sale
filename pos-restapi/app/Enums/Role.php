<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Manager => 'Manajer Outlet',
            self::Cashier => 'Kasir',
            self::Warehouse => 'Staf Gudang',
        };
    }

    /** Boleh memberi otorisasi PIN supervisor (void, refund, diskon di atas batas). */
    public function canAuthorize(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Boleh menambah/mengubah/menghapus produk & kategori. */
    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Boleh melihat stok, kartu stok, dan melakukan penyesuaian/opname/transfer. */
    public function canManageStock(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Warehouse], true);
    }

    /** Boleh melihat dashboard & laporan (omzet, laba). */
    public function canViewReports(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Boleh membuka manajemen karyawan. */
    public function canManageUsers(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /**
     * Role yang boleh diberikan/dikelola oleh role ini.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => self::cases(),
            self::Manager => [self::Cashier, self::Warehouse],
            default => [],
        };
    }

    /** Owner melihat semua outlet; role lain dibatasi outlet yang ditugaskan. */
    public function seesAllOutlets(): bool
    {
        return $this === self::Owner;
    }
}
