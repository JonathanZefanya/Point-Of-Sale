<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refund tidak pernah dihapus atau diubah; satu transaksi bisa punya beberapa refund (sebagian)
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('shift_id')->constrained();      // shift tempat uang dikeluarkan
            $table->foreignId('user_id')->constrained();       // kasir yang memproses
            $table->foreignId('approved_by')->constrained('users'); // supervisor (PIN)
            $table->string('method', 20);                      // metode pengembalian uang
            $table->unsignedBigInteger('amount');              // uang dikembalikan (rupiah)
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('sale_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('sale_refunds')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');  // nilai barang (tanpa pajak/service) yang diretur
            $table->unsignedBigInteger('cost_total');  // harga modal × qty (untuk koreksi laba)
            $table->boolean('restock');                // false = barang rusak, tidak kembali ke stok
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refund_items');
        Schema::dropIfExists('sale_refunds');
    }
};
