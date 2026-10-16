<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Kredensial integrasi (Midtrans, SMTP) dari Pengaturan → Integrasi; .env sebagai cadangan.
 * Nilai rahasia disimpan terenkripsi (APP_KEY) dan tidak pernah dikirim ke browser.
 */
final class Integrations
{
    /** Key pengaturan yang rahasia: dienkripsi saat disimpan, disembunyikan dari GET /settings. */
    public const SECRET_KEYS = ['midtrans.server_key', 'smtp.password'];

    public static function secret(string $key): ?string
    {
        $encrypted = Setting::get($key);
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null; // APP_KEY berganti: anggap belum diisi, pemilik perlu mengisi ulang
        }
    }

    public static function midtransServerKey(): ?string
    {
        return self::secret('midtrans.server_key') ?? (config('services.midtrans.server_key') ?: null);
    }

    /** Mode mengikuti sumber key: key dari aplikasi → pengaturan aplikasi; key dari .env → .env. */
    public static function midtransProduction(): bool
    {
        return self::secret('midtrans.server_key')
            ? (bool) Setting::get('midtrans.is_production', false)
            : (bool) config('services.midtrans.is_production');
    }

    /** Pakai SMTP dari Pengaturan bila host diisi; selain itu konfigurasi mail di .env. */
    public static function applySmtp(): void
    {
        $host = (string) Setting::get('smtp.host', '');
        if ($host === '') {
            return;
        }
        $encryption = Setting::get('smtp.encryption', 'tls');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::get('smtp.port', 587),
            'mail.mailers.smtp.username' => Setting::get('smtp.username') ?: null,
            'mail.mailers.smtp.password' => self::secret('smtp.password'),
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.from.address' => Setting::get('smtp.from_address') ?: config('mail.from.address'),
            'mail.from.name' => Setting::get('smtp.from_name') ?: config('mail.from.name'),
        ]);
        Mail::purge('smtp'); // buang mailer lama yang dibuat dengan konfigurasi sebelumnya
    }
}
