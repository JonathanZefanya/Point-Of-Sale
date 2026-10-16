<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** QRIS dinamis Midtrans (3.5): satu baris per QR yang dibuat kasir. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('midtrans');
            $table->string('order_id', 50)->unique();      // dikirim ke Midtrans
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete(); // terisi saat dipakai di transaksi
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending'); // pending | paid | expired | failed
            $table->text('qr_url')->nullable();
            $table->text('qr_string')->nullable();
            $table->string('transaction_id', 64)->nullable(); // id transaksi di Midtrans
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('last_notification')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
