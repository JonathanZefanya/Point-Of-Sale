<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Absensi & komisi karyawan (3.7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'clock_out_at']);
            $table->index(['outlet_id', 'clock_in_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Komisi dari penjualan bersih karyawan, mis. 1.50 = 1,5%
            $table->decimal('commission_percent', 5, 2)->default(0)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
        Schema::dropIfExists('attendances');
    }
};
