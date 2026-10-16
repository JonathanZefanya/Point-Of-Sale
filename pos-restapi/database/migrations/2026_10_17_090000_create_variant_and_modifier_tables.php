<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('name', 60);                    // mis. "Large", "Merah / XL"
            $table->string('sku', 50)->unique();           // unik termasuk varian terhapus
            $table->string('barcode', 50)->nullable()->index();
            $table->unsignedBigInteger('sell_price');
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);                    // mis. "Level Gula", "Topping"
            $table->unsignedTinyInteger('min_select')->default(0); // > 0 = wajib dipilih
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->timestamps();
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedBigInteger('price')->default(0); // tambahan harga (rupiah)
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('product_modifier_group', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['product_id', 'modifier_group_id']);
        });

        // Kolom variant_id sudah ada sejak awal (BE-1.4/1.7); sekarang tabelnya tersedia → pasang FK
        foreach (['product_stocks', 'stock_movements', 'sale_items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreign('variant_id')->references('id')->on('product_variants'));
        }
    }

    public function down(): void
    {
        foreach (['product_stocks', 'stock_movements', 'sale_items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropForeign(['variant_id']));
        }
        Schema::dropIfExists('product_modifier_group');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('product_variants');
    }
};
