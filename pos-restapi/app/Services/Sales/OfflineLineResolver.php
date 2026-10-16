<?php

namespace App\Services\Sales;

use App\Exceptions\BusinessException;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Baris transaksi offline (BE-3.2). Transaksi sudah terjadi di kasir, jadi:
 * - harga = harga perangkat saat offline (yang benar-benar dibayar pelanggan) — keputusan pemilik, lihat CLAUDE.md §10 Fase 3,
 * - produk/varian yang sudah dinonaktifkan atau dihapus tetap dicatat,
 * - beda harga dengan data server dikembalikan sebagai peringatan untuk ditinjau.
 */
class OfflineLineResolver
{
    /**
     * @param  list<array{product_id: int, variant_id?: ?int, modifier_ids?: ?list<int>, quantity: int, unit_price: int, note?: ?string}>  $items
     * @return array{0: list<array<string, mixed>>, 1: list<string>} [baris siap hitung, peringatan]
     */
    public function resolve(array $items, int $outletId): array
    {
        $products = Product::withTrashed()->with(['category', 'outletPrices'])->whereKey(array_column($items, 'product_id'))->get()->keyBy('id');
        $variants = ProductVariant::withTrashed()->whereKey(array_filter(array_column($items, 'variant_id')))->get()->keyBy('id');
        $modifiers = Modifier::query()->with('group')->whereKey(collect($items)->pluck('modifier_ids')->flatten()->filter()->all())->get()->keyBy('id');

        $lines = [];
        $warnings = [];
        foreach ($items as $i => $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw new BusinessException("Produk #{$item['product_id']} tidak ditemukan.", 422, ['errors' => ["items.{$i}.product_id" => ['Produk tidak ditemukan.']]]);
            }
            $variant = ! empty($item['variant_id']) ? $variants->get((int) $item['variant_id']) : null;
            $chosen = collect($item['modifier_ids'] ?? [])->map(fn ($id) => $modifiers->get((int) $id))->filter()
                ->map(fn ($m) => ['id' => $m->id, 'group' => $m->group?->name, 'name' => $m->name, 'price' => $m->price])->values()->all();
            $name = $variant ? "{$product->name} - {$variant->name}" : $product->name;

            $serverPrice = ($variant?->sell_price ?? $product->applyOutlet($outletId)->effectivePrice()) + array_sum(array_column($chosen, 'price'));
            if ((int) $item['unit_price'] !== $serverPrice) {
                $warnings[] = sprintf('Harga %s di perangkat Rp%s, di server Rp%s.', $name, number_format($item['unit_price'], 0, ',', '.'), number_format($serverPrice, 0, ',', '.'));
            }
            if ($product->trashed() || ! $product->is_active) {
                $warnings[] = "{$name} sudah tidak dijual saat transaksi disinkronkan.";
            }

            $lines[] = [
                'product' => $product,
                'variant_id' => $variant?->id,
                'name' => $name,
                'sku' => $variant?->sku ?? $product->sku,
                'quantity' => (int) $item['quantity'],
                'unit_price' => (int) $item['unit_price'],
                'cost_price' => $variant?->cost_price ?: $product->cost_price,
                'discount' => 0,
                'modifiers' => $chosen ?: null,
                'note' => $item['note'] ?? null,
            ];
        }

        return [$lines, $warnings];
    }
}
