<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockOpname;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stock opname (F8): mulai (snapshot produk berstok) → isi jumlah fisik (draf) → Terapkan.
 * Terapkan menetapkan stok ke jumlah fisik lewat StockService (movement `opname` per selisih).
 * Produk yang tidak diisi jumlahnya tidak diubah.
 */
class StockOpnameService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
        private readonly AuditLogger $audit,
    ) {}

    public function start(int $outletId, ?int $categoryId, ?string $note, User $user): StockOpname
    {
        return DB::transaction(function () use ($outletId, $categoryId, $note, $user) {
            $outlet = Outlet::query()->findOrFail($outletId);
            $number = $this->numbers->next('OPN', $outlet, StockOpname::class); // juga mengunci outlet

            $draft = StockOpname::query()->where('outlet_id', $outletId)->where('status', StockOpname::DRAFT)->first();
            if ($draft) {
                throw new BusinessException("Masih ada opname draf {$draft->number} di outlet ini. Lanjutkan atau batalkan dulu sebelum memulai yang baru.", 409, ['opname_id' => $draft->id]);
            }

            $opname = StockOpname::query()->create([
                'number' => $number, 'outlet_id' => $outletId, 'category_id' => $categoryId,
                'status' => StockOpname::DRAFT, 'note' => $note, 'user_id' => $user->id,
            ]);

            $rows = $this->snapshotRows($outletId, $categoryId);
            if (! $rows) {
                throw new BusinessException('Tidak ada produk yang dilacak stoknya untuk dihitung. Pilih kategori lain atau aktifkan "Lacak stok" di produk.', 422);
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                $opname->items()->insert(array_map(fn ($r) => [...$r, 'stock_opname_id' => $opname->id], $chunk));
            }

            return $opname;
        });
    }

    /** @param list<array{id: int, counted_quantity: ?int}> $counts */
    public function saveCounts(StockOpname $opname, array $counts, ?string $note): StockOpname
    {
        return DB::transaction(function () use ($opname, $counts, $note) {
            $opname = $this->lockDraft($opname);
            $items = $opname->items()->get()->keyBy('id');
            foreach ($counts as $count) {
                $item = $items->get((int) $count['id']);
                if ($item && $item->counted_quantity !== $count['counted_quantity']) {
                    $item->update(['counted_quantity' => $count['counted_quantity']]);
                }
            }
            if ($note !== null) {
                $opname->update(['note' => $note]);
            }
            $opname->touch();

            return $opname;
        });
    }

    public function apply(StockOpname $opname, User $user): StockOpname
    {
        return DB::transaction(function () use ($opname, $user) {
            $opname = $this->lockDraft($opname);
            $items = $opname->items()->whereNotNull('counted_quantity')->get()
                ->sortBy(fn ($i) => [$i->product_id, $i->variant_id ?? 0]); // urutan lock konsisten
            if ($items->isEmpty()) {
                throw new BusinessException('Belum ada jumlah fisik yang diisi. Isi minimal satu produk sebelum menerapkan opname.', 422);
            }

            $products = Product::withTrashed()->whereKey($items->pluck('product_id')->unique())->get()->keyBy('id');
            $plus = $minus = 0;
            foreach ($items as $item) {
                $movement = $this->stock->setQuantity($opname->outlet_id, $products[$item->product_id], $item->counted_quantity, StockMovementType::Opname, $opname, "Stock opname {$opname->number}", $item->variant_id);
                $difference = $movement?->quantity_change ?? 0;
                $item->update(['system_quantity' => $item->counted_quantity - $difference, 'difference' => $difference]);
                $difference > 0 ? $plus += $difference : $minus += $difference;
            }

            $opname->update(['status' => StockOpname::APPLIED, 'applied_by' => $user->id, 'applied_at' => now()]);
            $this->audit->log('stock_opname.applied', $opname, null, ['counted' => $items->count(), 'plus' => $plus, 'minus' => $minus]);

            return $opname;
        });
    }

    public function cancel(StockOpname $opname): StockOpname
    {
        return DB::transaction(function () use ($opname) {
            $opname = $this->lockDraft($opname);
            $opname->update(['status' => StockOpname::CANCELLED]);

            return $opname;
        });
    }

    /** Draf menampilkan saldo terkini (penjualan bisa terjadi selama menghitung); selisih = fisik − saldo. */
    public function withLiveQuantities(StockOpname $opname): StockOpname
    {
        $opname->loadMissing('items');
        if (! $opname->isDraft()) {
            return $opname;
        }
        $live = ProductStock::query()->where('outlet_id', $opname->outlet_id)->get()
            ->mapWithKeys(fn ($s) => [$s->product_id.':'.($s->variant_id ?? 0) => $s->quantity]);
        foreach ($opname->items as $item) {
            $item->system_quantity = (int) ($live[$item->product_id.':'.($item->variant_id ?? 0)] ?? 0);
            $item->difference = $item->counted_quantity === null ? null : $item->counted_quantity - $item->system_quantity;
        }

        return $opname;
    }

    private function lockDraft(StockOpname $opname): StockOpname
    {
        $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);
        if (! $opname->isDraft()) {
            throw new BusinessException("Opname {$opname->number} sudah ".strtolower(StockOpname::STATUS_LABELS[$opname->status]).' sehingga tidak bisa diubah.');
        }

        return $opname;
    }

    /** Satu baris per produk berstok (atau per varian untuk produk bervarian) dengan saldo saat ini. */
    private function snapshotRows(int $outletId, ?int $categoryId): array
    {
        $stock = ProductStock::query()->where('outlet_id', $outletId)->get()
            ->mapWithKeys(fn ($s) => [$s->product_id.':'.($s->variant_id ?? 0) => $s->quantity]);
        $products = Product::query()->with('variants')
            ->where('track_stock', true)->where('type', '!=', 'service')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')->get();

        $rows = [];
        foreach ($products as $p) {
            $targets = $p->variants->isNotEmpty() ? $p->variants : [null];
            foreach ($targets as $v) {
                $rows[] = [
                    'product_id' => $p->id,
                    'variant_id' => $v?->id,
                    'product_name' => $v ? "{$p->name} - {$v->name}" : $p->name,
                    'sku' => $v?->sku ?? $p->sku,
                    'barcode' => $v?->barcode ?? $p->barcode,
                    'system_quantity' => (int) ($stock[$p->id.':'.($v?->id ?? 0)] ?? 0),
                    'counted_quantity' => null,
                    'difference' => null,
                ];
            }
        }

        return $rows;
    }
}
