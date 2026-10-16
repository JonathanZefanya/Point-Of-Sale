<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pintu perubahan stok (CLAUDE.md §4). Setiap perubahan:
 * 1. dikunci (lockForUpdate) per baris product_stocks, berurutan menurut product_id lalu variant_id (cegah deadlock),
 * 2. divalidasi semua dulu — stok tidak boleh minus; jika ada yang kurang, tidak ada yang berubah
 *    (kecuali $allowNegative untuk sinkron transaksi offline yang sudah terjadi — BE-3.2),
 * 3. dicatat ke stock_movements lalu saldo product_stocks diperbarui.
 *
 * Stok produk bervarian disimpan per varian. Produk tanpa stok (jasa / lacak stok dimatikan) dilewati.
 */
class StockService
{
    /**
     * @param  list<StockChange>  $changes
     * @return list<StockMovement>
     *
     * @throws InsufficientStockException
     */
    public function apply(
        int $outletId,
        array $changes,
        StockMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        bool $allowNegative = false,
        ?array &$shortages = null,
    ): array {
        // Item yang sama bisa muncul lebih dari sekali (mis. dua baris di keranjang)
        $grouped = [];
        foreach ($changes as $change) {
            $grouped[$change->key()] ??= ['product' => $change->productId, 'variant' => $change->variantId, 'delta' => 0, 'cost' => null];
            $grouped[$change->key()]['delta'] += $change->quantityChange;
            $grouped[$change->key()]['cost'] = $change->unitCost ?? $grouped[$change->key()]['cost'];
        }
        uasort($grouped, fn ($a, $b) => [$a['product'], $a['variant'] ?? 0] <=> [$b['product'], $b['variant'] ?? 0]);

        return DB::transaction(function () use ($outletId, $grouped, $type, $reference, $note, $allowNegative, &$shortages) {
            $products = $this->trackedProducts(array_column($grouped, 'product'));
            $variants = $this->variants(array_filter(array_column($grouped, 'variant')));

            $rows = [];
            $shortages = [];
            foreach ($grouped as $key => $item) {
                $product = $products->get($item['product']);
                if (! $product || $item['delta'] === 0) {
                    continue;
                }

                $rows[$key] = $row = $this->lockRow($outletId, $item['product'], $item['variant']);

                if ($row->quantity + $item['delta'] < 0) {
                    $variant = $item['variant'] ? $variants->get($item['variant']) : null;
                    $shortages[] = [
                        'product_id' => $item['product'],
                        'variant_id' => $item['variant'],
                        'name' => $variant ? "{$product->name} - {$variant->name}" : $product->name,
                        'available' => $row->quantity,
                        'requested' => -$item['delta'],
                    ];
                }
            }

            if ($shortages && ! $allowNegative) {
                throw new InsufficientStockException($shortages);
            }

            $movements = [];
            foreach ($rows as $key => $row) {
                $item = $grouped[$key];
                $cost = $item['cost'] ?? ($item['variant'] ? ($variants->get($item['variant'])?->cost_price ?? 0) : $products->get($item['product'])->cost_price);
                $movements[] = $this->write($row, $item['delta'], $cost, $type, $reference, $note);
            }

            return $movements;
        });
    }

    /** Ubah stok satu produk/varian. Mengembalikan null bila produk tidak dilacak stoknya. */
    public function change(
        int $outletId,
        Product $product,
        int $quantityChange,
        StockMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        ?int $variantId = null,
    ): ?StockMovement {
        return $this->apply($outletId, [new StockChange($product->id, $quantityChange, $variantId)], $type, $reference, $note)[0] ?? null;
    }

    /**
     * Tetapkan stok ke angka tertentu (stock opname, hitung fisik).
     * Movement mencatat selisihnya; tidak ada movement bila sama.
     */
    public function setQuantity(
        int $outletId,
        Product $product,
        int $quantity,
        StockMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        ?int $variantId = null,
    ): ?StockMovement {
        return DB::transaction(function () use ($outletId, $product, $quantity, $type, $reference, $note, $variantId) {
            $current = $this->lockRow($outletId, $product->id, $variantId)->quantity;

            return $this->change($outletId, $product, max(0, $quantity) - $current, $type, $reference, $note, $variantId);
        });
    }

    /** Saldo saat ini (tanpa lock) — untuk tampilan, bukan untuk keputusan transaksi. */
    public function quantity(int $outletId, int $productId, ?int $variantId = null): int
    {
        return (int) ProductStock::query()
            ->where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId), fn ($q) => $q->whereNull('variant_id'))
            ->value('quantity');
    }

    /**
     * Produk yang dilacak stoknya, termasuk yang sudah dihapus (void/refund produk lama tetap mengembalikan stok).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function trackedProducts(array $ids): Collection
    {
        return Product::withTrashed()
            ->whereKey($ids)
            ->where('track_stock', true)
            ->get()
            ->filter(fn (Product $p) => $p->type->hasStock())
            ->keyBy('id');
    }

    /** @return Collection<int, ProductVariant> */
    private function variants(array $ids): Collection
    {
        return $ids ? ProductVariant::withTrashed()->whereKey($ids)->get()->keyBy('id') : new Collection;
    }

    /** Ambil baris stok dengan lock; buat baris 0 bila belum ada (dibuat sambil mengunci produk agar tidak dobel). */
    private function lockRow(int $outletId, int $productId, ?int $variantId): ProductStock
    {
        $query = fn () => ProductStock::query()
            ->where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId), fn ($q) => $q->whereNull('variant_id'))
            ->lockForUpdate();

        if ($row = $query()->first()) {
            return $row;
        }

        Product::withTrashed()->whereKey($productId)->lockForUpdate()->first();

        return $query()->first()
            ?? ProductStock::query()->create(['outlet_id' => $outletId, 'product_id' => $productId, 'variant_id' => $variantId, 'quantity' => 0]);
    }

    private function write(ProductStock $row, int $delta, int $cost, StockMovementType $type, ?Model $reference, ?string $note): StockMovement
    {
        $row->quantity += $delta;
        $row->save();

        return StockMovement::query()->create([
            'outlet_id' => $row->outlet_id,
            'product_id' => $row->product_id,
            'variant_id' => $row->variant_id,
            'type' => $type,
            'quantity_change' => $delta,
            'quantity_after' => $row->quantity,
            'cost_price' => $cost,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }
}
