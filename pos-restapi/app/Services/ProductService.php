<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    /** Field request yang bukan kolom tabel products. */
    private const NON_COLUMNS = ['image', 'remove_image', 'initial_stock', 'outlet_id', 'variants', 'modifier_group_ids', 'outlet_prices'];

    public const RELATIONS = ['category', 'variants', 'modifierGroups.modifiers', 'outletPrices'];

    public function __construct(
        private readonly SkuGenerator $skus,
        private readonly AuditLogger $audit,
        private readonly StockService $stock,
        private readonly ProductVariantService $variants,
    ) {}

    /** Varian & grup modifier hanya diubah bila dikirim di request. */
    private function syncRelations(Product $product, array $data): void
    {
        if (array_key_exists('variants', $data)) {
            $this->variants->sync($product, $data['variants'] ?? [], isset($data['outlet_id']) ? (int) $data['outlet_id'] : null);
        }
        if (array_key_exists('modifier_group_ids', $data)) {
            $product->modifierGroups()->sync(collect($data['modifier_group_ids'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
        }
        if (array_key_exists('outlet_prices', $data)) {
            $this->syncOutletPrices($product, $data['outlet_prices'] ?? []);
        }
    }

    /** Baris default (harga kosong & dijual) dihapus; perubahan harga outlet dicatat di audit log. */
    private function syncOutletPrices(Product $product, array $rows): void
    {
        $snapshot = fn () => $product->outletPrices()->orderBy('outlet_id')->get(['outlet_id', 'sell_price', 'is_available'])->toArray();
        $before = $snapshot();
        foreach ($rows as $row) {
            $isDefault = $row['sell_price'] === null && (bool) $row['is_available'];
            $isDefault
                ? $product->outletPrices()->where('outlet_id', $row['outlet_id'])->delete()
                : $product->outletPrices()->updateOrCreate(['outlet_id' => $row['outlet_id']], ['sell_price' => $row['sell_price'], 'is_available' => (bool) $row['is_available']]);
        }
        $after = $snapshot();
        if ($before !== $after) {
            $this->audit->log('product.outlet_prices_changed', $product, ['outlet_prices' => $before], ['outlet_prices' => $after]);
        }
        $product->unsetRelation('outletPrices');
    }

    /** @param array<string, mixed> $filters hasil validasi IndexProductRequest */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';

        return Product::query()
            ->with(self::RELATIONS)
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%")
                    ->orWhere('barcode', $s)
            ))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when(isset($filters['is_active']), fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->when($filters['outlet_id'] ?? null, fn (Builder $q, int $outletId) => $q->when($filters['available_only'] ?? false, fn (Builder $q) => $q->whereDoesntHave(
                'outletPrices', fn (Builder $q) => $q->where('outlet_id', $outletId)->where('is_available', false)
            )))
            ->paginate($filters['per_page'] ?? 20)
            ->through(fn (Product $p) => isset($filters['outlet_id']) ? $p->applyOutlet((int) $filters['outlet_id']) : $p);
    }

    /** Scan barcode di kasir. SKU juga diterima karena label rak sering memakai SKU. */
    public function findForScan(string $code, ?int $outletId = null): Product
    {
        $product = Product::query()
            ->with(self::RELATIONS)
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->orderByRaw('barcode = ? desc', [$code])
            ->first();

        // Barcode/SKU varian → produknya + varian yang cocok (kasir langsung menambah varian itu)
        if (! $product) {
            $variant = ProductVariant::query()
                ->where('is_active', true)
                ->where(fn (Builder $q) => $q->where('barcode', $code)->orWhere('sku', $code))
                ->whereHas('product', fn (Builder $q) => $q->where('is_active', true))
                ->first();
            $product = $variant?->product()->with(self::RELATIONS)->first();
            $product?->setAttribute('matched_variant_id', $variant->id);
        }

        if (! $product) {
            throw new BusinessException("Produk dengan barcode {$code} tidak ditemukan. Periksa barcode atau cari dengan nama produk.", 404);
        }
        if ($outletId && ! $product->applyOutlet($outletId)->availableAtOutlet) {
            throw new BusinessException("{$product->name} tidak dijual di outlet ini.", 404);
        }

        return $product;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Product
    {
        $autoSku = blank($data['sku'] ?? null);

        // SKU otomatis bisa bentrok bila dua produk disimpan bersamaan → coba lagi dengan nomor berikutnya
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->insert($data);
            } catch (UniqueConstraintViolationException $e) {
                if (! $autoSku || $attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function insert(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $data = $this->normalize($data);

            if (blank($data['sku'] ?? null)) {
                $data['sku'] = $this->skus->next(isset($data['category_id']) ? Category::query()->find($data['category_id']) : null);
            }

            $product = new Product(Arr::except($data, self::NON_COLUMNS));
            $product->image_path = $this->storeImage($data['image'] ?? null);
            $product->save();

            $this->audit->log('product.created', $product, null, $this->priceSnapshot($product));

            // Stok awal lewat StockService agar tercatat di kartu stok. Produk bervarian: stok awal per varian.
            if (($data['initial_stock'] ?? 0) > 0 && empty($data['variants'])) {
                $this->stock->change((int) $data['outlet_id'], $product, (int) $data['initial_stock'], StockMovementType::Initial, $product, 'Stok awal');
            }

            $this->syncRelations($product, $data);

            return $product->load(self::RELATIONS);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data): Product
    {
        $oldImage = null;

        $product = DB::transaction(function () use ($product, $data, &$oldImage) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $before = $this->priceSnapshot($product);
            $data = $this->normalize($data, $product);

            if (array_key_exists('sku', $data) && blank($data['sku'])) {
                unset($data['sku']); // SKU dikosongkan saat ubah → pertahankan yang lama
            }

            $product->fill(Arr::except($data, self::NON_COLUMNS));

            if (($data['image'] ?? null) instanceof UploadedFile || ($data['remove_image'] ?? false)) {
                $oldImage = $product->image_path;
                $product->image_path = $this->storeImage($data['image'] ?? null);
            }

            $product->save();

            $after = $this->priceSnapshot($product);
            if ($before !== $after) {
                $this->audit->log('product.price_changed', $product, $before, $after);
            }

            $this->syncRelations($product, $data);

            return $product;
        });

        // Hapus file lama setelah transaksi berhasil, agar tidak hilang jika simpan gagal
        if ($oldImage) {
            Storage::disk(Product::IMAGE_DISK)->delete($oldImage);
        }

        return $product->load(self::RELATIONS);
    }

    /** Soft delete: produk tetap ada di riwayat transaksi & kartu stok. */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->delete();
            $this->audit->log('product.deleted', $product, ['name' => $product->name, 'sku' => $product->sku]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?Product $product = null): array
    {
        $type = isset($data['type']) ? ProductType::from($data['type']) : ($product?->type ?? ProductType::Goods);

        // Jasa tidak punya stok
        if (! $type->hasStock()) {
            $data['track_stock'] = false;
        }

        return $data;
    }

    private function storeImage(?UploadedFile $file): ?string
    {
        return $file?->store('products', Product::IMAGE_DISK);
    }

    /** @return array{sell_price: int, cost_price: int} */
    private function priceSnapshot(Product $product): array
    {
        return ['sell_price' => (int) $product->sell_price, 'cost_price' => (int) $product->cost_price];
    }
}
