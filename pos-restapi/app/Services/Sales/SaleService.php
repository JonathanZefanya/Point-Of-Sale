<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\KitchenService;
use App\Services\LoyaltyService;
use App\Services\Payments\MidtransQrisService;
use App\Services\StockChange;
use App\Services\StockService;
use App\Services\Tables\TableOccupancy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi penjualan (F3 langkah 6–7). Semua dalam satu DB::transaction():
 * shift terbuka → produk masih dijual → hitung ulang total → validasi bayar →
 * nomor transaksi → simpan sale/items/payments → kurangi stok lewat StockService.
 */
class SaleService
{
    public function __construct(
        private readonly PaymentValidator $payments,
        private readonly SaleNumberGenerator $numbers,
        private readonly StockService $stock,
        private readonly LoyaltyService $loyalty,
        private readonly SaleQuoter $quoter,
        private readonly SupervisorAuthorizer $supervisors,
        private readonly AuditLogger $audit,
        private readonly TableOccupancy $tables,
        private readonly KitchenService $kitchen,
        private readonly MidtransQrisService $qris,
    ) {}

    /**
     * @param  array{outlet_id: int, items: list<array{product_id: int, quantity: int, note?: ?string}>, payments: list<array{method: string, amount: int, reference?: ?string}>, note?: ?string, idempotency_key?: ?string}  $data
     * @return array{0: Sale, 1: bool} [transaksi, baru dibuat?] — false bila idempotency key sudah pernah dipakai
     */
    public function checkout(User $user, array $data): array
    {
        $key = $data['idempotency_key'] ?? null;

        if ($key && $existing = $this->findByKey($user, $key)) {
            return [$existing, false];
        }

        try {
            return [$this->createCompleted($user, $data), true];
        } catch (UniqueConstraintViolationException $e) {
            // Dua request dengan key sama tiba bersamaan: yang kalah mengembalikan transaksi pemenang
            if ($key && $existing = $this->findByKey($user, $key)) {
                return [$existing, false];
            }
            throw $e;
        }
    }

    /** Diskon manual di atas batas kasir butuh PIN supervisor (2.5). */
    private function authorizeManualDiscount(array $quote, User $user, int $outletId, ?string $pin): ?User
    {
        if (! $quote['needs_supervisor']) {
            return null;
        }
        if (! $pin) {
            $message = 'Diskon manual melebihi batas kasir. Minta pemilik/manajer memasukkan PIN.';
            throw new BusinessException($message, 422, ['errors' => ['supervisor_pin' => [$message]]]);
        }

        return $this->supervisors->authorize($pin, $outletId, $user);
    }

    private function createCompleted(User $user, array $data): Sale
    {
        return DB::transaction(function () use ($user, $data) {
            $shift = $this->openShift($user, (int) $data['outlet_id']);
            $outlet = $shift->outlet;

            $quote = $this->quoter->quote($outlet, $data['items'], $user, $data['voucher_code'] ?? null, $data['manual_discount'] ?? null, lockVoucher: true);
            $calc = $quote['calc'];
            $approver = $this->authorizeManualDiscount($quote, $user, $outlet->id, $data['supervisor_pin'] ?? null);
            $change = $this->payments->changeFor($data['payments'], $calc->grandTotal);
            $now = now();

            // Pelanggan dikunci: poin tidak boleh terpakai dobel oleh dua kasir sekaligus
            $customer = isset($data['customer_id']) ? Customer::query()->lockForUpdate()->find($data['customer_id']) : null;
            $redeem = $this->loyalty->pointsToRedeem($data['payments'], $customer);

            // Transaksi tersimpan yang dilanjutkan: baris yang sama dijadikan transaksi selesai
            $sale = isset($data['held_sale_id']) ? $this->lockHeld((int) $data['held_sale_id'], $outlet->id) : new Sale;
            if ($sale->exists) {
                $sale->items()->delete(); // baris lama transaksi tersimpan diganti isi keranjang terbaru
            }
            $sale->fill([
                ...$calc->totals(),
                'number' => $this->numbers->next($outlet, $now),
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'status' => SaleStatus::Completed,
                'paid_total' => $calc->grandTotal + $change,
                'change_amount' => $change,
                'note' => $data['note'] ?? null,
                'order_type' => $data['order_type'] ?? $sale->order_type,
                'customer_id' => $data['customer_id'] ?? null,
                'voucher_id' => $quote['voucher']?->id,
                'manual_discount' => $quote['manual_discount'],
                'promotions_json' => $quote['applied'] ?: null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'completed_at' => $now,
            ])->save();

            $this->storeLines($sale, $calc);
            foreach ($data['payments'] as $payment) {
                if (isset($payment['intent_id'])) {
                    // QRIS dinamis: harus sudah lunas di Midtrans & belum dipakai (3.5)
                    $this->qris->consume((int) $payment['intent_id'], (int) $payment['amount'], $outlet->id, $sale->id);
                }
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            // Stok kurang → InsufficientStockException (409) → seluruh transaksi dibatalkan
            $this->stock->apply(
                $outlet->id,
                array_map(fn ($line) => new StockChange($line['product']->id, -$line['quantity'], $line['variant_id']), $calc->lines),
                StockMovementType::Sale,
                $sale,
                $sale->number,
            );

            if ($customer) {
                $this->loyalty->settleSale($sale, $customer, $redeem);
            }
            $quote['voucher']?->increment('used_count');
            if ($quote['manual_discount'] > 0) {
                $this->audit->log('sale.manual_discount', $sale, null, ['amount' => $quote['manual_discount'], 'approved_by' => $approver?->id]);
            }

            $this->kitchen->dispatch($sale); // Kitchen Display (3.4): item baru ke dapur

            return $sale->load(['items', 'payments', 'outlet', 'user', 'customer']);
        }, attempts: 3); // ulangi otomatis bila MySQL memilih transaksi ini sebagai korban deadlock
    }

    /**
     * Simpan keranjang sebagai transaksi tersimpan (F4). Tanpa nomor, pembayaran, atau perubahan stok.
     * Dengan held_sale_id: memperbarui transaksi tersimpan yang sedang dibuka.
     *
     * @param  array{outlet_id: int, items: list<array>, hold_label?: ?string, note?: ?string, held_sale_id?: ?int}  $data
     */
    public function hold(User $user, array $data): Sale
    {
        return DB::transaction(function () use ($user, $data) {
            $shift = $this->openShift($user, (int) $data['outlet_id']);
            $calc = $this->quoter->quote($shift->outlet, $data['items'], $user)['calc'];

            $sale = isset($data['held_sale_id']) ? $this->lockHeld((int) $data['held_sale_id'], $shift->outlet_id) : new Sale;
            // Bill meja (3.3): satu meja hanya satu bill terbuka
            $table = isset($data['table_id']) ? $this->tables->lockFree((int) $data['table_id'], $shift->outlet_id, $sale->id) : null;
            if ($sale->exists) {
                $sale->items()->delete(); // baris lama transaksi tersimpan diganti isi keranjang terbaru
            }
            $sale->fill([
                ...$calc->totals(),
                'outlet_id' => $shift->outlet_id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'status' => SaleStatus::Held,
                'hold_label' => $data['hold_label'] ?? null,
                'order_type' => $data['order_type'] ?? ($table ? 'dine_in' : null),
                'table_id' => $table?->id,
                'table_name' => $table?->name,
                'customer_id' => $data['customer_id'] ?? null,
                'note' => $data['note'] ?? null,
            ])->save();

            $this->storeLines($sale, $calc);
            $this->kitchen->dispatch($sale); // Kitchen Display (3.4): bill meja langsung ke dapur

            return $sale->load(['items', 'outlet', 'user', 'customer']);
        });
    }

    /** Buang transaksi tersimpan (draft) — bukan transaksi selesai, jadi boleh dihapus. */
    public function discardHeld(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $held = $this->lockHeld($sale->id, $sale->outlet_id);
            $held->items()->delete();
            $held->delete();
        });
    }

    private function lockHeld(int $saleId, int $outletId): Sale
    {
        $sale = Sale::query()->whereKey($saleId)->where('outlet_id', $outletId)->lockForUpdate()->first();

        if (! $sale || $sale->status !== SaleStatus::Held) {
            throw new BusinessException('Transaksi tersimpan ini sudah dilanjutkan atau dihapus di perangkat lain. Muat ulang daftar Tersimpan.');
        }

        return $sale;
    }

    public function storeLines(Sale $sale, CalculatedSale $calc): void
    {
        foreach ($calc->lines as $line) {
            $sale->items()->create([
                'product_id' => $line['product']->id,
                'variant_id' => $line['variant_id'],
                // Snapshot kategori untuk laporan per kategori (2.11)
                'category_id' => $line['product']->category_id,
                'category_name' => $line['product']->category?->name,
                'product_name' => $line['name'],
                'sku' => $line['sku'],
                'modifiers_json' => $line['modifiers'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'cost_price' => $line['cost_price'],
                'discount' => $line['discount'],
                'tax' => $line['tax'],
                'total' => $line['total'],
                'note' => $line['note'],
            ]);
        }
    }

    public function openShift(User $user, int $outletId): Shift
    {
        $shift = Shift::query()->open()->where('user_id', $user->id)->where('outlet_id', $outletId)
            ->with('outlet')->lockForUpdate()->first();

        if (! $shift) {
            throw new BusinessException('Buka shift terlebih dahulu sebelum mencatat transaksi.');
        }

        return $shift;
    }

    private function findByKey(User $user, string $key): ?Sale
    {
        $sale = Sale::query()->where('idempotency_key', $key)->with(['items', 'payments', 'outlet', 'user'])->first();

        if ($sale && $sale->user_id !== $user->id) {
            throw new BusinessException('Kode transaksi ini sudah dipakai kasir lain. Muat ulang layar kasir lalu coba lagi.');
        }

        return $sale;
    }
}
