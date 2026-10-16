<?php

namespace App\Exceptions;

/**
 * Stok tidak cukup (409). Body berisi `items` agar layar kasir bisa menandai
 * item bermasalah beserta sisa stoknya (F3 langkah 9).
 */
class InsufficientStockException extends BusinessException
{
    /**
     * @param  list<array{product_id: int, name: string, available: int, requested: int}>  $items
     */
    public function __construct(public readonly array $items)
    {
        parent::__construct(self::buildMessage($items), 409, ['items' => $items]);
    }

    private static function buildMessage(array $items): string
    {
        if (count($items) === 1) {
            return "Stok {$items[0]['name']} tidak cukup. Tersisa {$items[0]['available']}.";
        }

        $list = implode(', ', array_map(fn ($i) => "{$i['name']} (sisa {$i['available']})", $items));

        return "Stok beberapa produk tidak cukup: {$list}.";
    }
}
