<?php

namespace App\Services\Sales;

use App\Exceptions\BusinessException;
use App\Models\Product;

/**
 * Ubah item keranjang (product_id, variant_id, modifier_ids, quantity) menjadi baris siap hitung.
 * Server yang menentukan harga (CLAUDE.md §6): harga varian (atau harga outlet) + Σ harga modifier.
 * Error menunjuk baris keranjang yang bermasalah (items.N.*).
 */
class SaleLineResolver
{
    /**
     * @param  list<array{product_id: int, variant_id?: ?int, modifier_ids?: ?list<int>, quantity: int, note?: ?string}>  $items
     * @return list<array<string, mixed>>
     */
    public function resolve(array $items, ?int $outletId = null): array
    {
        $products = Product::query()
            ->whereKey(array_column($items, 'product_id'))
            ->with(['variants', 'modifierGroups.modifiers', 'outletPrices', 'category'])
            ->get()
            ->keyBy('id');

        $lines = [];
        $errors = [];
        foreach ($items as $i => $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! $product->is_active) {
                $errors["items.{$i}.product_id"] = [($product?->name ?? 'Produk ini').' tidak lagi dijual. Hapus dari keranjang.'];

                continue;
            }
            if ($outletId && ! $product->applyOutlet($outletId)->availableAtOutlet) {
                $errors["items.{$i}.product_id"] = ["{$product->name} tidak dijual di outlet ini. Hapus dari keranjang."];

                continue;
            }

            $error = null;
            $variant = $this->variant($product, $item['variant_id'] ?? null, $error);
            $modifiers = $error ? [] : $this->modifiers($product, $item['modifier_ids'] ?? [], $error);
            if ($error) {
                $errors["items.{$i}.{$error[0]}"] = [$error[1]];

                continue;
            }

            $lines[] = [
                'product' => $product,
                'variant_id' => $variant?->id,
                'name' => $variant ? "{$product->name} - {$variant->name}" : $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'quantity' => (int) $item['quantity'],
                'unit_price' => ($variant?->sell_price ?? $product->effectivePrice()) + array_sum(array_column($modifiers, 'price')),
                'cost_price' => $variant?->cost_price ?? $product->cost_price,
                'modifiers' => $modifiers ?: null,
                'note' => $item['note'] ?? null,
            ];
        }

        if ($errors) {
            throw new BusinessException('Ada item di keranjang yang perlu diperbaiki.', 422, ['errors' => $errors]);
        }

        return $lines;
    }

    /** Produk bervarian wajib memilih varian aktif miliknya; produk tanpa varian tidak boleh mengirim varian. */
    private function variant(Product $product, ?int $variantId, ?array &$error)
    {
        $active = $product->variants->where('is_active', true);

        if ($active->isEmpty()) {
            return null;
        }
        if (! $variantId) {
            $error = ['variant_id', "Pilih varian {$product->name} terlebih dahulu."];

            return null;
        }

        $variant = $active->firstWhere('id', $variantId);
        if (! $variant) {
            $error = ['variant_id', "Varian {$product->name} yang dipilih tidak lagi dijual. Pilih varian lain."];
        }

        return $variant;
    }

    /**
     * Modifier harus milik grup produk; jumlah per grup di antara min dan max.
     *
     * @return list<array{id: int, group: string, name: string, price: int}>
     */
    private function modifiers(Product $product, array $ids, ?array &$error): array
    {
        $ids = array_map('intval', array_unique($ids));
        $selected = [];

        foreach ($product->modifierGroups as $group) {
            $chosen = $group->modifiers->whereIn('id', $ids);
            $count = $chosen->count();

            if ($count < $group->min_select) {
                $error = ['modifier_ids', "Pilih {$group->name} untuk {$product->name} (minimal {$group->min_select})."];

                return [];
            }
            if ($count > $group->max_select) {
                $error = ['modifier_ids', "{$group->name} maksimal {$group->max_select} pilihan."];

                return [];
            }
            foreach ($chosen as $m) {
                $selected[] = ['id' => $m->id, 'group' => $group->name, 'name' => $m->name, 'price' => $m->price];
            }
        }

        if (count($selected) !== count($ids)) {
            $error = ['modifier_ids', "Ada tambahan yang tidak tersedia untuk {$product->name}. Pilih ulang."];

            return [];
        }

        return $selected;
    }
}
