<?php

namespace App\Services;

/**
 * Satu perubahan stok yang diminta: + menambah, − mengurangi. variantId untuk produk bervarian.
 * unitCost: harga modal yang dicatat di movement (mis. harga beli saat terima barang); null = harga modal produk.
 */
final readonly class StockChange
{
    public function __construct(
        public int $productId,
        public int $quantityChange,
        public ?int $variantId = null,
        public ?int $unitCost = null,
    ) {}

    public function key(): string
    {
        return $this->productId.':'.($this->variantId ?? 0);
    }
}
