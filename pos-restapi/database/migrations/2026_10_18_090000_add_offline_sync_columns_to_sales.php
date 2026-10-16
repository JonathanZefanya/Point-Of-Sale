<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Transaksi offline (BE-3.2): kapan disinkronkan + catatan konflik (harga beda, stok minus, shift sudah tutup). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('completed_at');
            $table->json('sync_warnings')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['synced_at', 'sync_warnings']);
        });
    }
};
