<?php

namespace App\Services;

use App\Models\ModifierGroup;
use Illuminate\Support\Facades\DB;

class ModifierGroupService
{
    /** @param array{name: string, min_select: int, max_select: int, modifiers: list<array{id?: ?int, name: string, price?: ?int}>} $data */
    public function save(ModifierGroup $group, array $data): ModifierGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->fill(['name' => $data['name'], 'min_select' => $data['min_select'], 'max_select' => $data['max_select']])->save();

            $existing = $group->modifiers()->get()->keyBy('id');
            $keep = [];
            foreach (array_values($data['modifiers']) as $position => $row) {
                $values = ['name' => trim($row['name']), 'price' => (int) ($row['price'] ?? 0), 'sort_order' => $position];
                $modifier = ! empty($row['id']) && $existing->has($row['id'])
                    ? tap($existing->get($row['id']))->update($values)
                    : $group->modifiers()->create($values);
                $keep[] = $modifier->id;
            }
            $group->modifiers()->whereKeyNot($keep)->delete();

            return $group->load('modifiers')->loadCount('products');
        });
    }
}
