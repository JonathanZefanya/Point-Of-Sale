<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stock opname dengan draf (2.7, F8). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('applied_by')->nullable()->constrained('users');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'status']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->string('product_name', 160);
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->integer('system_quantity'); // saat mulai; diperbarui ke saldo saat diterapkan
            $table->integer('counted_quantity')->nullable(); // null = belum dihitung, tidak diubah
            $table->integer('difference')->nullable();
            $table->unique(['stock_opname_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
