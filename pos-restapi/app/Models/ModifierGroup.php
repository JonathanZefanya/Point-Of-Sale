<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grup add-on, mis. "Level Gula" (wajib pilih 1) atau "Topping" (opsional, maks 3). */
class ModifierGroup extends Model
{
    protected $fillable = ['name', 'min_select', 'max_select'];

    protected function casts(): array
    {
        return ['min_select' => 'integer', 'max_select' => 'integer'];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class, 'group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_group');
    }
}
