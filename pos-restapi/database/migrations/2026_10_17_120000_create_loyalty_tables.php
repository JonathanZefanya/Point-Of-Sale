<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('points_earned')->default(0)->after('change_amount');
            $table->unsignedBigInteger('points_redeemed')->default(0)->after('points_earned');
        });

        // Riwayat poin pelanggan (seperti kartu stok): tidak pernah diubah atau dihapus
        Schema::create('point_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('sale_id')->nullable()->constrained();
            $table->string('type', 20);            // earn | redeem | void | refund
            $table->bigInteger('points');          // + bertambah, − berkurang
            $table->unsignedBigInteger('balance_after');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_movements');
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['points_earned', 'points_redeemed']));
    }
};
