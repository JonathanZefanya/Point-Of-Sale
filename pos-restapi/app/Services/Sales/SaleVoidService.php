<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AuditLogger;
use App\Services\LoyaltyService;
use App\Services\StockChange;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Void transaksi (F5): hanya transaksi selesai di shift yang masih terbuka, alasan wajib,
 * disetujui PIN supervisor. Stok dikembalikan; uang dianggap dikembalikan dari laci
 * (transaksi void tidak dihitung di kas seharusnya).
 */
class SaleVoidService
{
    public function __construct(
        private readonly SupervisorAuthorizer $supervisors,
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function void(Sale $sale, User $requester, string $reason, string $supervisorPin): Sale
    {
        $supervisor = $this->supervisors->authorize($supervisorPin, $sale->outlet_id, $requester);

        return DB::transaction(function () use ($sale, $reason, $supervisor) {
            $sale = Sale::query()->with(['items', 'shift'])->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== SaleStatus::Completed) {
                throw new BusinessException("Transaksi {$sale->number} berstatus {$sale->status->label()} dan tidak bisa dibatalkan.");
            }
            if (! $sale->shift?->isOpen()) {
                throw new BusinessException("Transaksi {$sale->number} dari shift yang sudah ditutup. Gunakan refund untuk mengembalikan barang.");
            }

            $sale->update([
                'status' => SaleStatus::Void,
                'voided_by' => $supervisor->id,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            $this->stock->apply(
                $sale->outlet_id,
                $sale->items->map(fn ($item) => new StockChange($item->product_id, $item->quantity, $item->variant_id))->all(),
                StockMovementType::SaleVoid,
                $sale,
                "Batal {$sale->number}",
            );

            $this->loyalty->reverseSale($sale);
            if ($sale->voucher_id) {
                // Kuota voucher dikembalikan karena transaksinya batal
                Voucher::query()->whereKey($sale->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
            }

            $this->audit->log('sale.voided', $sale, ['status' => SaleStatus::Completed->value], [
                'status' => SaleStatus::Void->value,
                'reason' => $reason,
                'grand_total' => $sale->grand_total,
                'approved_by' => $supervisor->id,
            ]);

            return $sale->load(['items', 'payments', 'outlet', 'user', 'voider']);
        });
    }
}
