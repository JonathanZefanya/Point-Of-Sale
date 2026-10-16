<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('user_id')->constrained();      // kasir pemilik shift
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opening_cash');        // modal awal (rupiah)
            $table->unsignedBigInteger('expected_cash')->nullable(); // dihitung sistem saat tutup
            $table->unsignedBigInteger('actual_cash')->nullable();   // dihitung kasir saat tutup
            $table->bigInteger('difference')->nullable();            // actual − expected (bisa minus)
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['outlet_id', 'user_id', 'closed_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained();
            $table->string('type', 3);                      // in | out
            $table->unsignedBigInteger('amount');           // rupiah
            $table->string('note');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
    }
};
