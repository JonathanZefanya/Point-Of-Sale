<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat poin pelanggan. Hanya ditambah oleh LoyaltyService. */
class PointMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'sale_id', 'type', 'points', 'balance_after', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
