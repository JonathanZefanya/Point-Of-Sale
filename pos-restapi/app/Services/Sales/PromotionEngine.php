<?php

namespace App\Services\Sales;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\Voucher;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Menerapkan promo ke baris keranjang (2.5). Aturan kombinasi:
 * 1. Promo item: satu promo terbaik per baris.
 * 2. Buy X Get Y: ditambahkan di atas promo item (diskon baris maksimal = nilai baris).
 * 3. Promo transaksi: satu terbesar; voucher (bila dipakai) menggantikan promo transaksi otomatis.
 * 4. Diskon manual: terakhir, dari sisa setelah promo.
 * Diskon transaksi & manual dibagi ke baris secara proporsional agar laporan per produk tetap benar.
 */
class PromotionEngine
{
    /**
     * @param  list<array<string, mixed>>  $lines  dari SaleLineResolver (punya product, quantity, unit_price)
     * @param  array{type: string, value: int}|null  $manual  diskon manual: percent (0–100) atau amount (rupiah)
     * @return array{lines: list<array<string, mixed>>, applied: list<array<string, mixed>>, manual_discount: int, order_discount: int}
     */
    public function apply(array $lines, int $outletId, CarbonInterface $at, ?Voucher $voucher = null, ?array $manual = null): array
    {
        $promos = Promotion::query()->running($at)->where('requires_voucher', false)->get()
            ->filter(fn (Promotion $p) => $p->appliesAt($outletId, $at));
        $voucherPromo = $voucher?->promotion;

        foreach ($lines as $i => $line) {
            $lines[$i]['discount'] = 0;
        }
        $applied = [];

        // 1. Promo item (termasuk voucher bertipe item)
        $itemPromos = $promos->filter(fn ($p) => $p->type->isItemLevel());
        if ($voucherPromo?->type->isItemLevel()) {
            $itemPromos->push($voucherPromo);
        }
        foreach ($lines as $i => $line) {
            [$best, $promo] = $this->bestItemDiscount($line, $itemPromos);
            if ($best > 0) {
                $lines[$i]['discount'] = $best;
                $applied[$promo->id] = $this->snapshot($promo, ($applied[$promo->id]['amount'] ?? 0) + $best);
            }
        }

        // 2. Beli X gratis Y
        foreach ($promos->filter(fn ($p) => $p->type === PromotionType::BuyXGetY) as $promo) {
            $amount = $this->applyBuyXGetY($lines, $promo);
            if ($amount > 0) {
                $applied[$promo->id] = $this->snapshot($promo, $amount);
            }
        }

        // 3. Promo transaksi: voucher menggantikan promo otomatis
        $net = $this->net($lines);
        $orderPromos = $voucherPromo?->type->isOrderLevel()
            ? collect([$voucherPromo])
            : $promos->filter(fn ($p) => $p->type->isOrderLevel());
        [$orderDiscount, $orderPromo] = $this->bestOrderDiscount($net, $orderPromos);
        if ($orderPromo) {
            $applied[$orderPromo->id] = $this->snapshot($orderPromo, $orderDiscount);
        }

        // 4. Diskon manual dari sisa
        $remaining = $net - $orderDiscount;
        $manualDiscount = match ($manual['type'] ?? null) {
            'percent' => intdiv($remaining * min(100, $manual['value']) * 2 + 100, 200),
            'amount' => min($remaining, $manual['value']),
            default => 0,
        };

        $lines = $this->allocate($lines, $orderDiscount + $manualDiscount);

        return ['lines' => $lines, 'applied' => array_values($applied), 'manual_discount' => $manualDiscount, 'order_discount' => $orderDiscount];
    }

    /** @return array{0: int, 1: ?Promotion} */
    private function bestItemDiscount(array $line, Collection $promos): array
    {
        $gross = $line['unit_price'] * $line['quantity'];
        $best = [0, null];
        foreach ($promos as $promo) {
            if (! $this->matchesProduct($promo, $line)) {
                continue;
            }
            $amount = $promo->type->isPercent()
                ? intdiv($gross * min(100, $promo->value) * 2 + 100, 200)
                : min($gross, $promo->value * $line['quantity']);
            if ($amount > $best[0]) {
                $best = [$amount, $promo];
            }
        }

        return $best;
    }

    private function matchesProduct(Promotion $promo, array $line): bool
    {
        $products = array_map('intval', $promo->rule('product_ids', []));
        $categories = array_map('intval', $promo->rule('category_ids', []));
        if (! $products && ! $categories) {
            return true;
        }

        return in_array($line['product']->id, $products, true)
            || ($line['product']->category_id && in_array((int) $line['product']->category_id, $categories, true));
    }

    /** Gratis Y untuk setiap X yang dibeli; produk sama → setiap X+Y unit, Y gratis. */
    private function applyBuyXGetY(array &$lines, Promotion $promo): int
    {
        $buyId = (int) $promo->rule('buy_product_id');
        $getId = (int) $promo->rule('get_product_id');
        $buyQty = max(1, (int) $promo->rule('buy_quantity', 1));
        $getQty = max(1, (int) $promo->rule('get_quantity', 1));

        $bought = collect($lines)->filter(fn ($l) => $l['product']->id === $buyId)->sum('quantity');
        $sets = $buyId === $getId ? intdiv($bought, $buyQty + $getQty) : intdiv($bought, $buyQty);
        $freeLeft = $sets * $getQty;
        $total = 0;

        foreach ($lines as $i => $line) {
            if ($freeLeft <= 0 || $line['product']->id !== $getId) {
                continue;
            }
            $free = min($freeLeft, $line['quantity']);
            $gross = $line['unit_price'] * $line['quantity'];
            $add = min($free * $line['unit_price'], $gross - $line['discount']);
            $lines[$i]['discount'] += $add;
            $total += $add;
            $freeLeft -= $free;
        }

        return $total;
    }

    /** @return array{0: int, 1: ?Promotion} */
    private function bestOrderDiscount(int $net, Collection $promos): array
    {
        $best = [0, null];
        foreach ($promos as $promo) {
            if ($net < (int) $promo->rule('min_purchase', 0)) {
                continue;
            }
            $amount = $promo->type->isPercent() ? intdiv($net * min(100, $promo->value) * 2 + 100, 200) : $promo->value;
            $max = $promo->rule('max_discount');
            $amount = min($amount, $net, $max ? (int) $max : PHP_INT_MAX);
            if ($amount > $best[0]) {
                $best = [$amount, $promo];
            }
        }

        return $best;
    }

    /** Bagi diskon transaksi ke baris proporsional terhadap nilai bersih baris; sisa pembulatan ke baris terakhir. */
    private function allocate(array $lines, int $amount): array
    {
        $net = $this->net($lines);
        if ($amount <= 0 || $net <= 0) {
            return $lines;
        }
        $given = 0;
        $last = array_key_last($lines);
        foreach ($lines as $i => $line) {
            $lineNet = $line['unit_price'] * $line['quantity'] - $line['discount'];
            $share = $i === $last ? $amount - $given : intdiv($amount * $lineNet * 2 + $net, $net * 2);
            $share = min($share, $lineNet);
            $lines[$i]['discount'] += $share;
            $given += $share;
        }

        return $lines;
    }

    private function net(array $lines): int
    {
        return array_sum(array_map(fn ($l) => $l['unit_price'] * $l['quantity'] - $l['discount'], $lines));
    }

    private function snapshot(Promotion $promo, int $amount): array
    {
        return ['id' => $promo->id, 'name' => $promo->name, 'type' => $promo->type->value, 'amount' => $amount];
    }
}
