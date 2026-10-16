<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saldo stok per outlet. Hanya diubah oleh App\Services\StockService.
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('variant_id')->nullable(); // FK ditambahkan saat tabel varian ada (Fase 2)
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['outlet_id', 'product_id', 'variant_id']);
        });

        // Kartu stok: setiap perubahan tercatat, tidak pernah diubah/dihapus.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('type', 20);                       // App\Enums\StockMovementType
            $table->integer('quantity_change');               // + masuk, − keluar
            $table->integer('quantity_after');                // saldo setelah movement ini
            $table->unsignedBigInteger('cost_price')->default(0); // snapshot harga modal saat itu (rupiah)
            $table->nullableMorphs('reference');              // mis. Sale, StockOpname, PurchaseOrder
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['outlet_id', 'product_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
    }
};
