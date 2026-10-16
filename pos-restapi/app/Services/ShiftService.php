<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessException;
use App\Models\CashMovement;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Buka shift, kas masuk/keluar, tutup shift (F2 & F6). */
class ShiftService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ShiftSalesSummary $sales,
        private readonly AttendanceService $attendance,
    ) {}

    public function current(User $user, int $outletId): ?Shift
    {
        return Shift::query()
            ->open()
            ->where('user_id', $user->id)
            ->where('outlet_id', $outletId)
            ->with(['outlet', 'user', 'cashMovements'])
            ->first();
    }

    /** Default modal awal = kas fisik dihitung di shift terakhir yang ditutup di outlet ini (F2.2). */
    public function suggestedOpeningCash(int $outletId): int
    {
        return (int) Shift::query()
            ->where('outlet_id', $outletId)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->latest('id')
            ->value('actual_cash');
    }

    public function open(User $user, int $outletId, int $openingCash): Shift
    {
        return DB::transaction(function () use ($user, $outletId, $openingCash) {
            // Kunci baris user agar dua klik "Mulai Shift" bersamaan tidak membuat dua shift
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $existing = Shift::query()->open()->where('user_id', $user->id)->where('outlet_id', $outletId)->first();
            if ($existing) {
                throw new BusinessException(
                    "Anda masih punya shift terbuka sejak {$existing->opened_at->format('d/m H:i')}. Lanjutkan shift itu atau tutup terlebih dahulu.",
                );
            }

            // Absensi (3.7): mulai shift = absen masuk bila belum
            $this->attendance->ensureClockedIn($user, $outletId);

            return Shift::query()->create([
                'outlet_id' => $outletId,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
            ])->load(['outlet', 'user', 'cashMovements']);
        });
    }

    public function addCashMovement(Shift $shift, CashMovementType $type, int $amount, string $note): CashMovement
    {
        return DB::transaction(function () use ($shift, $type, $amount, $note) {
            $shift = $this->lockOpen($shift);

            $movement = $shift->cashMovements()->create([
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
                'user_id' => auth()->id(),
            ]);

            if ($type === CashMovementType::Out) {
                $this->audit->log('cash.out', $shift, null, ['amount' => $amount, 'note' => $note]);
            }

            return $movement;
        });
    }

    /**
     * Tutup shift: kasir memasukkan kas fisik, sistem menghitung selisih.
     * Catatan wajib bila selisih ≠ 0 (F6.3).
     */
    public function close(Shift $shift, int $actualCash, ?string $note): Shift
    {
        try {
            return $this->closeLocked($shift, $actualCash, $note);
        } catch (BusinessException $e) {
            // Dicatat di luar transaksi (yang sudah di-rollback) agar hitungan ulang kasir tetap terlacak
            if (isset($e->extra['difference'])) {
                $this->audit->log('shift.count_mismatch', $shift, null, [
                    'actual_cash' => $actualCash,
                    'expected_cash' => $e->extra['expected_cash'],
                    'difference' => $e->extra['difference'],
                ]);
            }
            throw $e;
        }
    }

    private function closeLocked(Shift $shift, int $actualCash, ?string $note): Shift
    {
        return DB::transaction(function () use ($shift, $actualCash, $note) {
            $shift = $this->lockOpen($shift);
            $expected = $this->expectedCash($shift);
            $difference = $actualCash - $expected;

            // Ada selisih tanpa catatan: tampilkan angka sistem + selisih (kas fisik sudah diinput), minta catatan
            if ($difference !== 0 && blank($note)) {
                throw new BusinessException('Kas fisik berbeda dari catatan sistem.', 422, [
                    'errors' => ['note' => ['Kas fisik berbeda dari catatan sistem. Tulis catatan penyebab selisihnya.']],
                    'expected_cash' => $expected,
                    'actual_cash' => $actualCash,
                    'difference' => $difference,
                ]);
            }

            $shift->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'difference' => $difference,
                'note' => $note,
                'closed_by' => auth()->id(),
            ]);

            $this->audit->log('shift.closed', $shift, null, [
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'difference' => $difference,
            ]);

            return $shift->load(['outlet', 'user', 'cashMovements']);
        });
    }

    /** Kas yang seharusnya ada di laci: modal + kas masuk − kas keluar + penjualan tunai bersih. */
    public function expectedCash(Shift $shift): int
    {
        $totals = $this->cashTotals($shift);

        return $shift->opening_cash + $totals['in'] - $totals['out'] + $this->sales->netCash($shift);
    }

    /**
     * Laporan shift untuk dicetak (X/Z report). Angka kas sistem hanya untuk shift yang sudah ditutup.
     *
     * @return array<string, mixed>
     */
    public function report(Shift $shift): array
    {
        $totals = $this->cashTotals($shift);
        $sales = $this->sales->summary($shift);

        // Selama shift terbuka, penjualan tunai disembunyikan: dari situ kas seharusnya bisa dihitung (F6.2)
        if ($shift->isOpen()) {
            $sales['payments'] = array_values(array_filter($sales['payments'], fn ($p) => $p['method'] !== 'cash'));
        }

        return [
            'opening_cash' => $shift->opening_cash,
            'cash_in' => $totals['in'],
            'cash_out' => $totals['out'],
            ...$sales,
            ...($shift->isOpen() ? [] : [
                'expected_cash' => $shift->expected_cash,
                'actual_cash' => $shift->actual_cash,
                'difference' => $shift->difference,
            ]),
        ];
    }

    /** @return array{in: int, out: int} */
    private function cashTotals(Shift $shift): array
    {
        $sums = $shift->cashMovements()->reorder()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS total_out")
            ->toBase()
            ->first();

        return ['in' => (int) $sums->total_in, 'out' => (int) $sums->total_out];
    }

    private function lockOpen(Shift $shift): Shift
    {
        $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);

        if (! $shift->isOpen()) {
            throw new BusinessException('Shift ini sudah ditutup. Buka shift baru untuk melanjutkan.');
        }

        return $shift;
    }
}
