<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot kategori di sale_items (2.11): laporan per kategori tidak bergantung pada kategori produk saat ini.
 * Baris lama diisi dari kategori produk saat migrasi dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('variant_id');
            $table->string('category_name', 100)->nullable()->after('category_id');
        });

        DB::table('sale_items')->orderBy('id')->chunkById(500, function ($items) {
            $products = DB::table('products')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->whereIn('products.id', $items->pluck('product_id')->unique())
                ->get(['products.id', 'products.category_id', 'categories.name'])->keyBy('id');
            foreach ($items as $item) {
                $p = $products->get($item->product_id);
                if ($p?->category_id) {
                    DB::table('sale_items')->where('id', $item->id)->update(['category_id' => $p->category_id, 'category_name' => $p->name]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['category_id', 'category_name']);
        });
    }
};
