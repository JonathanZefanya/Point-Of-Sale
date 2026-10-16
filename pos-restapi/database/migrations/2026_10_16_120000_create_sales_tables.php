<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penomoran transaksi per outlet per hari: {KODE}-{YYYYMMDD}-{urut 4 digit}
        Schema::create('sale_sequences', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained();
            $table->date('sale_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['outlet_id', 'sale_date']);
        });

        // Semua nilai uang dalam integer rupiah. Transaksi tidak pernah dihapus — hanya void.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique(); // diberikan saat transaksi selesai
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('customer_id')->nullable()->index(); // FK ditambahkan saat modul pelanggan (Fase 2)
            $table->string('status', 20)->index();
            $table->string('order_type', 20)->nullable();
            $table->string('hold_label', 60)->nullable();      // label transaksi tersimpan (F4)
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('service_charge')->default(0);
            $table->bigInteger('rounding')->default(0);        // bisa minus (dibulatkan ke bawah)
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->string('note', 500)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique(); // cegah transaksi dobel saat double submit
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['outlet_id', 'status', 'completed_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('variant_id')->nullable();
            // Snapshot saat transaksi — laporan tidak bergantung pada data produk saat ini
            $table->string('product_name', 150);
            $table->string('sku', 50);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('cost_price');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);   // porsi pajak baris ini
            $table->unsignedBigInteger('total');             // quantity × unit_price − discount
            $table->json('modifiers_json')->nullable();
            $table->string('note', 200)->nullable();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20)->index();
            $table->unsignedBigInteger('amount');
            $table->string('reference', 100)->nullable(); // no. approval EDC / ref QRIS
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('sale_sequences');
    }
};
