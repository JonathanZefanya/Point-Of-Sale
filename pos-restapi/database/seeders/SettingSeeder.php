<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Pengaturan default. Hanya mengisi key yang belum ada, sehingga aman dijalankan ulang
 * tanpa menimpa pengaturan yang sudah diubah user.
 */
class SettingSeeder extends Seeder
{
    public const DEFAULTS = [
        'business.name' => 'Toko Saya',
        'business.address' => '',
        'business.phone' => '',
        'business.npwp' => '',

        'tax.enabled' => true,
        'tax.inclusive' => false,    // false = pajak ditambahkan di atas harga
        'service_charge.enabled' => false,

        'rounding.enabled' => false,
        'rounding.to' => 100,        // dibulatkan ke kelipatan Rp100
        'rounding.mode' => 'nearest', // nearest | up | down

        'receipt.paper_width' => 58, // 58 | 80 (mm)
        'receipt.auto_print' => false,
        'receipt.show_cashier' => true,

        'loyalty.enabled' => true,
        'loyalty.earn_per' => 10000,   // setiap belanja Rp10.000 = 1 poin
        'loyalty.point_value' => 100,  // 1 poin = Rp100 saat ditukar

        // Notifikasi email (3.8): penerima dipisah koma
        'notifications.recipients' => '',
        'notifications.daily_report' => false,
        'notifications.low_stock' => false,

        // Integrasi (Pengaturan → Integrasi). Server key & password SMTP disimpan terenkripsi, tanpa default.
        'midtrans.is_production' => false,
        'smtp.host' => '',        // kosong = pakai konfigurasi mail di .env
        'smtp.port' => 587,
        'smtp.username' => '',
        'smtp.encryption' => 'tls', // tls | ssl
        'smtp.from_address' => '',
        'smtp.from_name' => '',

        'kitchen.enabled' => false, // Kitchen Display (3.4): kirim pesanan ke layar dapur

        'discount.manual_limit_percent' => 10, // di atas ini butuh PIN supervisor
        'pin.max_attempts' => 5,
        'pin.lockout_minutes' => 5,
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
