<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/** Penyesuaian stok manual (F8) — aksi sensitif, dicatat ke audit_logs. */
class StockAdjustmentService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
    ) {}

    public function adjust(int $outletId, Product $product, int $quantityChange, AdjustmentReason $reason, ?string $note, ?int $variantId = null): StockMovement
    {
        return DB::transaction(function () use ($outletId, $product, $quantityChange, $reason, $note, $variantId) {
            $text = $note ? "{$reason->label()}: {$note}" : $reason->label();
            $movement = $this->stock->change($outletId, $product, $quantityChange, StockMovementType::Adjustment, null, $text, $variantId);

            $this->audit->log('stock.adjusted', $product, ['quantity' => $movement->quantity_after - $quantityChange], [
                'outlet_id' => $outletId,
                'variant_id' => $variantId,
                'quantity' => $movement->quantity_after,
                'quantity_change' => $quantityChange,
                'reason' => $reason->value,
                'note' => $note,
            ]);

            return $movement;
        });
    }
}
