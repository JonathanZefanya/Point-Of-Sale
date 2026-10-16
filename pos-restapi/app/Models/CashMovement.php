<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kas masuk/keluar di tengah shift (F6). Tidak pernah diubah atau dihapus. */
class CashMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['shift_id', 'type', 'amount', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
