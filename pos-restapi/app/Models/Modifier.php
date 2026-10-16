<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Modifier extends Model
{
    public $timestamps = false;

    protected $fillable = ['group_id', 'name', 'price', 'sort_order'];

    protected function casts(): array
    {
        return ['group_id' => 'integer', 'price' => 'integer', 'sort_order' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'group_id');
    }
}
