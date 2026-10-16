<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Http\Requests\Sale\SyncSalesRequest;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoyaltyService;
use App\Services\StockChange;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Sinkron transaksi offline (BE-3.2). Setiap transaksi diproses dalam transaksi DB sendiri:
 * satu gagal tidak menggagalkan yang lain. client_id = idempotency key → kirim ulang aman.
 *
 * Kebijakan konflik (keputusan pemilik): transaksi tetap dicatat dengan harga perangkat; stok boleh minus;
 * semua konflik dicatat di sales.sync_warnings + audit log `sale.offline_conflict` untuk ditinjau.
 */
class OfflineSyncService
{
    public function __construct(
        private readonly OfflineLineResolver $lines,
        private readonly SaleCalculator $calculator,
        private readonly SaleNumberGenerator $numbers,
        private readonly StockService $stock,
        private readonly LoyaltyService $loyalty,
        private readonly AuditLogger $audit,
        private readonly SaleService $sales,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $sales  hasil validasi SyncSalesRequest
     * @return list<array{client_id: string, status: string, sale?: array, warnings?: list<string>, message?: string}>
     */
    public function sync(User $user, array $sales): array
    {
        return array_map(function (array $data) use ($user) {
            $invalid = Validator::make($data, SyncSalesRequest::saleRules($user));
            if ($invalid->fails()) {
                return ['client_id' => (string) ($data['client_id'] ?? ''), 'status' => 'failed', 'message' => $invalid->errors()->first()];
            }
            try {
                if ($existing = $this->existing($user, $data['client_id'])) {
                    return ['client_id' => $data['client_id'], 'status' => 'duplicate', 'sale' => $this->summary($existing), 'warnings' => $existing->sync_warnings ?? []];
                }
                $sale = $this->store($user, $data);

                return ['client_id' => $data['client_id'], 'status' => 'created', 'sale' => $this->summary($sale), 'warnings' => $sale->sync_warnings ?? []];
            } catch (UniqueConstraintViolationException) {
                // Perangkat mengirim ulang bersamaan: yang kalah mengembalikan transaksi pemenang
                $existing = $this->existing($user, $data['client_id']);

                return ['client_id' => $data['client_id'], 'status' => $existing ? 'duplicate' : 'failed', 'sale' => $existing ? $this->summary($existing) : null];
            } catch (BusinessException $e) {
                return ['client_id' => $data['client_id'], 'status' => 'failed', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);

                return ['client_id' => $data['client_id'], 'status' => 'failed', 'message' => 'Terjadi kesalahan di server saat menyimpan transaksi ini. Coba sinkron lagi nanti.'];
            }
        }, $sales);
    }

    private function store(User $user, array $data): Sale
    {
        return DB::transaction(function () use ($user, $data) {
            $outlet = Outlet::query()->findOrFail($data['outlet_id']);
            $shift = Shift::query()->whereKey($data['shift_id'])->where('user_id', $user->id)->where('outlet_id', $outlet->id)->lockForUpdate()->first();
            if (! $shift) {
                throw new BusinessException('Shift transaksi ini bukan milik kasir ini di outlet tersebut.');
            }

            [$lines, $warnings] = $this->lines->resolve($data['items'], $outlet->id);
            $calc = $this->calculator->calculate($lines, PricingSettings::forOutlet($outlet));
            $paid = array_sum(array_column($data['payments'], 'amount'));
            if (isset($data['grand_total']) && (int) $data['grand_total'] !== $calc->grandTotal) {
                $warnings[] = sprintf('Total di perangkat Rp%s, dihitung server Rp%s (pengaturan pajak/pembulatan mungkin berubah).', $this->rp($data['grand_total']), $this->rp($calc->grandTotal));
            }
            if ($paid < $calc->grandTotal) {
                $warnings[] = sprintf('Pembayaran tercatat Rp%s, kurang Rp%s dari total server.', $this->rp($paid), $this->rp($calc->grandTotal - $paid));
            }
            if (! $shift->isOpen()) {
                $warnings[] = 'Shift sudah ditutup sebelum transaksi ini tersinkron; laporan kas shift tidak memuat transaksi ini.';
            }

            $completedAt = CarbonImmutable::parse($data['completed_at']);
            $customer = isset($data['customer_id']) ? Customer::query()->lockForUpdate()->find($data['customer_id']) : null;

            $sale = Sale::query()->create([
                ...$calc->totals(),
                'number' => $this->numbers->next($outlet, $completedAt),
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'status' => SaleStatus::Completed,
                'paid_total' => $paid,
                'change_amount' => max(0, $paid - $calc->grandTotal),
                'note' => $data['note'] ?? null,
                'idempotency_key' => $data['client_id'],
                'completed_at' => $completedAt,
                'synced_at' => now(),
            ]);
            $this->sales->storeLines($sale, $calc);
            foreach ($data['payments'] as $payment) {
                $sale->payments()->create(['method' => $payment['method'], 'amount' => $payment['amount'], 'reference' => $payment['reference'] ?? null]);
            }

            // Barang sudah diserahkan: stok boleh minus, kekurangannya dicatat sebagai konflik
            $shortages = [];
            $this->stock->apply(
                $outlet->id,
                array_map(fn ($l) => new StockChange($l['product']->id, -$l['quantity'], $l['variant_id']), $calc->lines),
                StockMovementType::Sale, $sale, "{$sale->number} (offline)", allowNegative: true, shortages: $shortages,
            );
            foreach ($shortages as $s) {
                $warnings[] = "Stok {$s['name']} tidak cukup saat sinkron (tersisa {$s['available']}, terjual {$s['requested']}); stok menjadi minus.";
            }

            if ($customer) {
                $this->loyalty->settleSale($sale, $customer, 0);
            }
            if ($warnings) {
                $sale->update(['sync_warnings' => $warnings]);
                $this->audit->log('sale.offline_conflict', $sale, null, ['warnings' => $warnings]);
            }

            return $sale;
        }, attempts: 3);
    }

    private function existing(User $user, string $clientId): ?Sale
    {
        $sale = Sale::query()->where('idempotency_key', $clientId)->first();
        if ($sale && $sale->user_id !== $user->id) {
            throw new BusinessException('Kode transaksi offline ini sudah dipakai kasir lain.');
        }

        return $sale;
    }

    private function summary(Sale $sale): array
    {
        return ['id' => $sale->id, 'number' => $sale->number, 'grand_total' => $sale->grand_total, 'completed_at' => $sale->completed_at];
    }

    private function rp(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
