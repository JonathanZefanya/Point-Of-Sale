<?php

use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Laporan terjadwal & notifikasi stok (3.8). Jalankan scheduler: php artisan schedule:work
Artisan::command('pos:daily-report {date? : Tanggal laporan, default kemarin}', function (NotificationService $notifications) {
    $date = $this->argument('date') ?: now()->subDay()->toDateString();
    $sent = $notifications->sendDailyReports($date);
    $this->info($sent ? "Laporan harian {$date} terkirim untuk {$sent} outlet." : 'Tidak ada yang dikirim (laporan harian mati atau penerima kosong).');
})->purpose('Kirim laporan penjualan harian via email');

Artisan::command('pos:low-stock-alert', function (NotificationService $notifications) {
    $sent = $notifications->sendLowStockAlerts();
    $this->info($sent ? "Peringatan stok terkirim untuk {$sent} outlet." : 'Tidak ada peringatan stok yang dikirim.');
})->purpose('Kirim peringatan stok menipis via email');

Schedule::command('pos:daily-report')->dailyAt('07:00');
Schedule::command('pos:low-stock-alert')->dailyAt('08:00');
