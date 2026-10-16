<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * Kategori sedikit dan dipakai sebagai tab di kasir, jadi dikembalikan semua (tanpa paginasi).
     *
     * @return Collection<int, Category>
     */
    public function all(?string $search = null): Collection
    {
        return Category::query()
            ->withCount('products')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @param array{name: string, sort_order?: int} $data */
    public function create(array $data): Category
    {
        $data['sort_order'] ??= (int) Category::query()->max('sort_order') + 1; // baru → paling akhir

        return Category::query()->create($data)->loadCount('products');
    }

    /** @param array<string, mixed> $data */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->loadCount('products');
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $used = $category->products()->count(); // hanya produk yang belum dihapus

            if ($used > 0) {
                throw new BusinessException(
                    "Kategori {$category->name} masih dipakai {$used} produk. Pindahkan produknya ke kategori lain terlebih dahulu.",
                );
            }

            $category->delete();
        });
    }
}
