<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 50)->unique();         // unik termasuk produk terhapus (riwayat transaksi)
            $table->string('barcode', 50)->nullable()->index();
            $table->string('name', 150)->index();
            $table->string('type', 20)->default('goods'); // goods | service | composite
            $table->string('unit', 20)->default('pcs');
            $table->unsignedBigInteger('cost_price')->default(0); // rupiah, integer
            $table->unsignedBigInteger('sell_price');             // rupiah, integer
            $table->boolean('track_stock')->default(true);
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
