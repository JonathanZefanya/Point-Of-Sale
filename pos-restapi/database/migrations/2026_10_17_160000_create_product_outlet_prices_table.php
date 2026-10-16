<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga & ketersediaan per outlet (2.8). Tidak ada baris = harga default produk & dijual.
 * sell_price null = pakai harga default (hanya ketersediaan yang diatur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_outlet_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sell_price')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_outlet_prices');
    }
};
