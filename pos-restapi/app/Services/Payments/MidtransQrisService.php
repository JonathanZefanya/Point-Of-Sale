<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Outlet;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Support\Integrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * QRIS dinamis via Midtrans Core API (3.5).
 * Status lunas diterima dari webhook; saat kasir menunggu, status juga ditanyakan langsung ke Midtrans
 * (berguna bila webhook tidak bisa menjangkau server, mis. di jaringan lokal).
 */
class MidtransQrisService
{
    public function enabled(): bool
    {
        return filled(Integrations::midtransServerKey());
    }

    public function create(User $user, int $outletId, int $amount): PaymentIntent
    {
        if (! $this->enabled()) {
            throw new BusinessException('QRIS dinamis belum diaktifkan. Isi MIDTRANS_SERVER_KEY di server, atau pakai QRIS statis dan isi no. referensi.');
        }
        $outlet = Outlet::query()->findOrFail($outletId);
        $minutes = (int) config('services.midtrans.qris_expiry_minutes', 15);
        $intent = PaymentIntent::query()->create([
            'order_id' => sprintf('POS-%s-%s-%s', $outlet->code, now()->format('ymdHis'), Str::upper(Str::random(4))),
            'outlet_id' => $outlet->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => 'pending',
            'expires_at' => now()->addMinutes($minutes),
        ]);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/v2/charge', [
            'payment_type' => 'qris',
            'transaction_details' => ['order_id' => $intent->order_id, 'gross_amount' => $amount],
            'qris' => ['acquirer' => 'gopay'],
            'custom_expiry' => ['expiry_duration' => $minutes, 'unit' => 'minute'],
        ]));
        if (! in_array((string) ($response['status_code'] ?? ''), ['200', '201'], true)) {
            $intent->update(['status' => 'failed']);
            throw new BusinessException('Midtrans menolak pembuatan QR: '.($response['status_message'] ?? 'tanpa keterangan').'. Coba lagi atau pakai QRIS statis.');
        }

        $intent->update([
            'transaction_id' => $response['transaction_id'] ?? null,
            'qr_string' => $response['qr_string'] ?? null,
            'qr_url' => collect($response['actions'] ?? [])->firstWhere('name', 'generate-qr-code')['url'] ?? null,
        ]);

        return $intent;
    }

    /** Status terkini; bila masih menunggu, tanyakan ke Midtrans. */
    public function refresh(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->status === 'pending' && $this->enabled()) {
            try {
                $response = $this->send(fn (PendingRequest $http) => $http->get("/v2/{$intent->order_id}/status"));
                $this->apply($intent, $response);
            } catch (BusinessException) {
                // Gangguan sementara ke Midtrans: kasir tetap menunggu, dicoba lagi pada polling berikutnya
            }
        }
        if ($intent->status === 'pending' && $intent->expires_at?->isPast()) {
            $intent->update(['status' => 'expired']);
        }

        return $intent;
    }

    /** Webhook Midtrans. Tanda tangan: sha512(order_id + status_code + gross_amount + server_key). */
    public function notify(array $payload): ?PaymentIntent
    {
        $expected = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').Integrations::midtransServerKey());
        if (! $this->enabled() || ! hash_equals($expected, (string) ($payload['signature_key'] ?? ''))) {
            throw new BusinessException('Tanda tangan notifikasi tidak valid.', 403);
        }

        return DB::transaction(function () use ($payload) {
            $intent = PaymentIntent::query()->where('order_id', $payload['order_id'])->lockForUpdate()->first();
            if ($intent) {
                $this->apply($intent, $payload);
            }

            return $intent;
        });
    }

    /**
     * Tandai QR lunas terpakai di transaksi. Dipanggil di dalam transaksi DB checkout.
     * QR harus lunas, milik outlet ini, nominal sama, dan belum pernah dipakai.
     */
    public function consume(int $intentId, int $amount, int $outletId, int $saleId): void
    {
        $intent = PaymentIntent::query()->whereKey($intentId)->lockForUpdate()->first();
        $fail = fn (string $m) => throw new BusinessException($m, 422, ['errors' => ['payments' => [$m]]]);

        if (! $intent || $intent->outlet_id !== $outletId) {
            $fail('Pembayaran QRIS tidak ditemukan untuk outlet ini. Buat QR baru.');
        }
        if ($intent->status !== 'paid') {
            $fail('Pembayaran QRIS belum lunas. Tunggu pelanggan menyelesaikan pembayaran.');
        }
        if ($intent->amount !== $amount) {
            $fail('Nominal QRIS berbeda dengan pembayaran. Buat QR baru sesuai sisa tagihan.');
        }
        if ($intent->sale_id && $intent->sale_id !== $saleId) {
            $fail('Pembayaran QRIS ini sudah dipakai di transaksi lain.');
        }
        $intent->update(['sale_id' => $saleId]);
    }

    private function apply(PaymentIntent $intent, array $data): void
    {
        $status = match ($data['transaction_status'] ?? null) {
            'settlement', 'capture' => 'paid',
            'expire' => 'expired',
            'cancel', 'deny', 'failure' => 'failed',
            default => 'pending',
        };
        // Nominal harus sama persis; status lunas tidak pernah diturunkan
        if ($status === 'paid' && (int) round((float) ($data['gross_amount'] ?? 0)) !== $intent->amount) {
            $status = 'failed';
        }
        if ($intent->status === 'paid') {
            $status = 'paid';
        }
        $intent->update([
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($intent->paid_at ?? now()) : null,
            'transaction_id' => $data['transaction_id'] ?? $intent->transaction_id,
            'last_notification' => $data,
        ]);
    }

    /** @param callable(PendingRequest): Response $call */
    private function send(callable $call): array
    {
        $base = Integrations::midtransProduction() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        try {
            $response = $call(Http::baseUrl($base)->withBasicAuth((string) Integrations::midtransServerKey(), '')->acceptJson()->timeout(15));
        } catch (ConnectionException) {
            throw new BusinessException('Tidak dapat terhubung ke Midtrans. Periksa koneksi internet server, atau pakai QRIS statis.');
        }

        return $response->json() ?? [];
    }
}
