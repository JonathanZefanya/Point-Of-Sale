<?php

namespace App\Services\Notifications;

use App\Exceptions\BusinessException;
use App\Http\Resources\StockResource;
use App\Mail\DailyReportMail;
use App\Mail\LowStockMail;
use App\Models\Outlet;
use App\Models\Setting;
use App\Services\Reports\ReportService;
use App\Services\StockQueryService;
use App\Support\Integrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Laporan terjadwal via email & notifikasi stok menipis (3.8).
 * Dikirim langsung (tanpa queue) dari perintah terjadwal — lihat routes/console.php.
 */
class NotificationService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly StockQueryService $stock,
    ) {}

    /** @return list<string> alamat email penerima dari Pengaturan (dipisah koma/spasi/baris baru) */
    public function recipients(): array
    {
        $raw = (string) Setting::get('notifications.recipients', '');

        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $raw)), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    /** Kirim laporan harian semua outlet untuk $date. @return int jumlah email terkirim */
    public function sendDailyReports(string $date, bool $force = false): int
    {
        $to = $this->recipients();
        if (! $to || (! $force && ! Setting::get('notifications.daily_report', false))) {
            return 0;
        }
        Integrations::applySmtp(); // SMTP dari Pengaturan → Integrasi bila diisi
        $sent = 0;
        foreach (Outlet::query()->orderBy('code')->get() as $outlet) {
            Mail::to($to)->send(new DailyReportMail($outlet, $date, $this->dailyData($outlet, $date)));
            $sent++;
        }

        return $sent;
    }

    /** Kirim peringatan stok menipis per outlet yang punya produk di bawah minimum. @return int */
    public function sendLowStockAlerts(): int
    {
        $to = $this->recipients();
        if (! $to || ! Setting::get('notifications.low_stock', false)) {
            return 0;
        }
        Integrations::applySmtp(); // SMTP dari Pengaturan → Integrasi bila diisi
        $sent = 0;
        foreach (Outlet::query()->orderBy('code')->get() as $outlet) {
            $items = $this->lowStock($outlet->id, 100);
            if ($items) {
                Mail::to($to)->send(new LowStockMail($outlet, $items));
                $sent++;
            }
        }

        return $sent;
    }

    /** Uji dari halaman Pengaturan: laporan hari ini ke penerima, walau laporan harian dimatikan. */
    public function sendTest(): int
    {
        if (! $this->recipients()) {
            throw new BusinessException('Isi alamat email penerima di Pengaturan → Notifikasi email, lalu simpan.', 422, ['errors' => ['notifications.recipients' => ['Isi minimal satu email penerima.']]]);
        }

        try {
            return $this->sendDailyReports(now()->toDateString(), force: true);
        } catch (TransportExceptionInterface $e) {
            throw new BusinessException('Email gagal dikirim: '.$e->getMessage().' Periksa host, port, enkripsi, username, dan password SMTP di Pengaturan → Integrasi.', 422);
        }
    }

    private function dailyData(Outlet $outlet, string $date): array
    {
        return [
            'metrics' => $this->reports->metrics($outlet->id, $date, $date),
            'gross_profit' => $this->reports->grossProfit($outlet->id, $date, $date),
            'payments' => $this->reports->payments($outlet->id, $date, $date),
            'top_products' => $this->reports->topProducts($outlet->id, $date, $date),
            'low_stock' => $this->lowStock($outlet->id, 10),
        ];
    }

    /** Produk habis & menipis (status `low` sudah mencakup habis), paling sedikit dulu. */
    private function lowStock(int $outletId, int $limit): array
    {
        $page = $this->stock->paginate(['outlet_id' => $outletId, 'status' => 'low', 'sort' => 'quantity', 'per_page' => $limit]);

        return StockResource::collection($page->items())->resolve(new Request);
    }
}
