<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** Label Bahasa Indonesia untuk audit log (2.12). Aksi yang belum dikenal tampil apa adanya. */
final class AuditLabels
{
    public const ACTIONS = [
        'sale.voided' => 'Transaksi dibatalkan (void)',
        'sale.refunded' => 'Refund transaksi',
        'sale.manual_discount' => 'Diskon manual',
        'cash.out' => 'Kas keluar',
        'shift.closed' => 'Tutup shift',
        'shift.count_mismatch' => 'Selisih kas shift',
        'stock.adjusted' => 'Penyesuaian stok',
        'stock_opname.applied' => 'Stock opname diterapkan',
        'stock_transfer.created' => 'Transfer stok dikirim',
        'stock_transfer.received' => 'Transfer stok diterima',
        'stock_transfer.cancelled' => 'Transfer stok dibatalkan',
        'purchase_order.received' => 'Barang PO diterima',
        'purchase_order.cancelled' => 'PO dibatalkan',
        'product.created' => 'Produk ditambahkan',
        'product.price_changed' => 'Harga produk diubah',
        'product.outlet_prices_changed' => 'Harga per outlet diubah',
        'product.deleted' => 'Produk dihapus',
        'product.imported' => 'Import produk',
        'promotion.created' => 'Promo ditambahkan',
        'promotion.updated' => 'Promo diubah',
        'outlet.created' => 'Outlet ditambahkan',
        'outlet.updated' => 'Outlet diubah',
        'outlet.deleted' => 'Outlet dihapus',
        'user.created' => 'Karyawan ditambahkan',
        'user.updated' => 'Karyawan diubah',
        'user.deleted' => 'Karyawan dihapus',
        'settings.updated' => 'Pengaturan diubah',
    ];

    public const MODELS = [
        'sale' => 'Transaksi', 'refund' => 'Refund', 'product' => 'Produk', 'category' => 'Kategori', 'user' => 'Karyawan',
        'outlet' => 'Outlet', 'shift' => 'Shift', 'promotion' => 'Promo', 'purchase_order' => 'PO',
        'stock_opname' => 'Stock opname', 'stock_transfer' => 'Transfer stok',
    ];

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    /** Mis. "Transaksi JKT01-20261015-0042", "Produk Kopi Susu", atau "Produk #12" bila datanya sudah tidak ada. */
    public static function subject(?string $type, ?int $id, ?Model $model): ?string
    {
        if (! $type) {
            return null;
        }
        $label = self::MODELS[$type] ?? $type;
        $name = $model?->getAttribute('number') ?? $model?->getAttribute('name');

        return $name ? "{$label} {$name}" : "{$label} #{$id}";
    }
}
