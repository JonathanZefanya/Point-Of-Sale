<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kitchen Display (3.4): tiket dapur berisi item yang baru dikirim dari kasir.
 * sales.kitchen_sent_json mencatat jumlah per item yang sudah dikirim → tambahan pesanan jadi tiket baru, tidak dobel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 60);                 // nama meja / label bill / nomor transaksi
            $table->string('order_type', 20)->nullable();
            $table->string('status', 20)->default('new'); // new | preparing | ready | served
            $table->json('items');                       // [{name, quantity, modifiers: [], note}]
            $table->boolean('is_addition')->default(false); // tambahan pesanan untuk bill yang sama
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->json('kitchen_sent_json')->nullable()->after('sync_warnings');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('kitchen_sent_json');
        });
        Schema::dropIfExists('kitchen_tickets');
    }
};
