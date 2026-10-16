<?php

namespace App\Services\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Sale;
use App\Models\SaleRefund;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoyaltyService;
use App\Services\StockChange;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Refund sebagian/penuh (F5). Nilai uang dihitung server, proporsional terhadap grand total
 * (ikut pajak, service, pembulatan); refund terakhir mengambil sisanya agar total refund = grand total.
 */
class SaleRefundService
{
    public function __construct(
        private readonly SupervisorAuthorizer $supervisors,
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
        private readonly LoyaltyService $loyalty,
    ) {}

    /**
     * @param  list<array{sale_item_id: int, quantity: int, restock?: bool}>  $lines
     */
    public function refund(Sale $sale, User $requester, array $lines, PaymentMethod $method, string $reason, string $supervisorPin): SaleRefund
    {
        $supervisor = $this->supervisors->authorize($supervisorPin, $sale->outlet_id, $requester);

        return DB::transaction(function () use ($sale, $requester, $lines, $method, $reason, $supervisor) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->load(['items' => fn ($q) => $q->withSum('refundItems', 'quantity')]);

            if (! in_array($sale->status, [SaleStatus::Completed, SaleStatus::PartialRefund], true)) {
                throw new BusinessException("Transaksi {$sale->number} berstatus {$sale->status->label()} dan tidak bisa di-refund.");
            }

            $shift = Shift::query()->open()->where('user_id', $requester->id)->where('outlet_id', $sale->outlet_id)->first();
            if (! $shift) {
                throw new BusinessException('Buka shift di outlet ini terlebih dahulu: uang refund dicatat keluar dari shift yang sedang berjalan.');
            }

            [$rows, $lineTotal] = $this->rows($sale, $lines);
            $amount = $this->amount($sale, $rows, $lineTotal);

            $refund = SaleRefund::query()->create([
                'sale_id' => $sale->id,
                'outlet_id' => $sale->outlet_id,
                'shift_id' => $shift->id,
                'user_id' => $requester->id,
                'approved_by' => $supervisor->id,
                'method' => $method,
                'amount' => $amount,
                'reason' => $reason,
            ]);
            foreach ($rows as $row) {
                $refund->items()->create(collect($row)->except('item')->all());
            }

            $this->loyalty->refund($sale, $amount, $method);

            $restock = array_filter($rows, fn ($r) => $r['restock']);
            if ($restock) {
                $this->stock->apply(
                    $sale->outlet_id,
                    array_map(fn ($r) => new StockChange($r['item']->product_id, $r['quantity'], $r['item']->variant_id), array_values($restock)),
                    StockMovementType::Refund,
                    $refund,
                    "Retur {$sale->number}",
                );
            }

            $fullyRefunded = $sale->items->every(fn ($item) => $item->refundedQuantity() + ($rows[$item->id]['quantity'] ?? 0) >= $item->quantity);
            $sale->update(['status' => $fullyRefunded ? SaleStatus::Refunded : SaleStatus::PartialRefund]);

            $this->audit->log('sale.refunded', $sale, null, [
                'refund_id' => $refund->id,
                'amount' => $amount,
                'method' => $method->value,
                'reason' => $reason,
                'approved_by' => $supervisor->id,
                'items' => array_map(fn ($r) => ['sale_item_id' => $r['sale_item_id'], 'quantity' => $r['quantity'], 'restock' => $r['restock']], array_values($rows)),
            ]);

            return $refund->load(['items', 'approver']);
        });
    }

    /**
     * Validasi jumlah per item (≤ sisa yang belum di-refund) dan siapkan baris refund.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function rows(Sale $sale, array $lines): array
    {
        $items = $sale->items->keyBy('id');
        $rows = [];
        $errors = [];
        $lineTotal = 0;

        foreach ($lines as $i => $line) {
            $item = $items->get((int) $line['sale_item_id']);
            $qty = (int) $line['quantity'];
            if (! $item) {
                $errors["items.{$i}.sale_item_id"] = ['Item ini bukan bagian dari transaksi.'];

                continue;
            }
            $remaining = $item->quantity - $item->refundedQuantity() - ($rows[$item->id]['quantity'] ?? 0);
            if ($qty > $remaining) {
                $errors["items.{$i}.quantity"] = ["{$item->product_name}: maksimal {$remaining} yang masih bisa dikembalikan."];

                continue;
            }

            $value = intdiv($item->total * $qty * 2 + $item->quantity, $item->quantity * 2); // half-up
            $lineTotal += $value;
            $rows[$item->id] = [
                'item' => $item,
                'sale_item_id' => $item->id,
                'quantity' => ($rows[$item->id]['quantity'] ?? 0) + $qty,
                'line_total' => ($rows[$item->id]['line_total'] ?? 0) + $value,
                'cost_total' => ($rows[$item->id]['cost_total'] ?? 0) + $item->cost_price * $qty,
                'restock' => (bool) ($line['restock'] ?? true),
            ];
        }

        if ($errors) {
            throw new BusinessException('Jumlah refund tidak valid.', 422, ['errors' => $errors]);
        }

        return [$rows, $lineTotal];
    }

    /** Uang dikembalikan: porsi grand total; bila refund ini menghabiskan semua item → sisa persis. */
    private function amount(Sale $sale, array $rows, int $lineTotal): int
    {
        $alreadyRefunded = (int) $sale->refunds()->sum('amount');
        $everything = $sale->items->every(fn ($item) => $item->refundedQuantity() + ($rows[$item->id]['quantity'] ?? 0) >= $item->quantity);

        if ($everything) {
            return max(0, $sale->grand_total - $alreadyRefunded);
        }

        $share = $sale->subtotal > 0 ? intdiv($sale->grand_total * $lineTotal * 2 + $sale->subtotal, $sale->subtotal * 2) : 0;

        return min($share, $sale->grand_total - $alreadyRefunded);
    }
}
