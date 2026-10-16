<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const IMAGE_DISK = 'public';

    /** Sama dengan default kolom DB, agar model baru langsung lengkap tanpa refresh. */
    protected $attributes = [
        'type' => 'goods',
        'unit' => 'pcs',
        'cost_price' => 0,
        'track_stock' => true,
        'min_stock' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'category_id',
        'sku',
        'barcode',
        'name',
        'type',
        'unit',
        'cost_price',
        'sell_price',
        'track_stock',
        'min_stock',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'category_id' => 'integer',
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'track_stock' => 'boolean',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Semua varian (termasuk nonaktif); varian terhapus tidak ikut. */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group')
            ->withPivot('sort_order')
            ->orderBy('product_modifier_group.sort_order');
    }

    public function outletPrices(): HasMany
    {
        return $this->hasMany(ProductOutletPrice::class);
    }

    /** Harga khusus outlet (diisi applyOutlet); null = harga default. Tidak disimpan ke DB. */
    public ?int $outletPrice = null;

    /** Dijual di outlet yang diterapkan (diisi applyOutlet); null = tanpa konteks outlet. */
    public ?bool $availableAtOutlet = null;

    /**
     * Terapkan harga & ketersediaan outlet (2.8). Harga khusus hanya untuk produk tanpa varian;
     * produk bervarian memakai harga varian.
     */
    public function applyOutlet(int $outletId): static
    {
        $override = $this->relationLoaded('outletPrices')
            ? $this->outletPrices->firstWhere('outlet_id', $outletId)
            : $this->outletPrices()->where('outlet_id', $outletId)->first();
        $this->outletPrice = $override?->sell_price;
        $this->availableAtOutlet = $override?->is_available ?? true;

        return $this;
    }

    public function effectivePrice(): int
    {
        return $this->outletPrice ?? $this->sell_price;
    }

    /** Produk dengan varian aktif: stok & harga per varian, varian wajib dipilih saat dijual. */
    public function hasVariants(): bool
    {
        return $this->variants->where('is_active', true)->isNotEmpty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(self::IMAGE_DISK)->url($this->image_path) : null;
    }
}
