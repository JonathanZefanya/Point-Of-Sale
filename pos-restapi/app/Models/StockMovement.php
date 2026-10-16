<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Satu baris kartu stok. Hanya ditambah oleh StockService, tidak pernah diubah atau dihapus. */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'outlet_id',
        'product_id',
        'variant_id',
        'type',
        'quantity_change',
        'quantity_after',
        'cost_price',
        'reference_type',
        'reference_id',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'outlet_id' => 'integer',
            'product_id' => 'integer',
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
            'cost_price' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
