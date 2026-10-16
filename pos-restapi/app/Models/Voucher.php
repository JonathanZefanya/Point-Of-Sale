<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = ['code', 'promotion_id', 'quota', 'used_count'];

    protected function casts(): array
    {
        return ['quota' => 'integer', 'used_count' => 'integer'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function exhausted(): bool
    {
        return $this->quota !== null && $this->used_count >= $this->quota;
    }
}
