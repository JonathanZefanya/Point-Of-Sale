<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur F&B (3.3): meja per outlet. Bill meja = transaksi tersimpan (held) dengan table_id;
 * meja terisi selama ada bill tersimpan di meja itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->string('name', 30);
            $table->string('area', 40)->nullable(); // mis. Indoor, Teras, Lantai 2
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['outlet_id', 'sort_order']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable()->after('customer_id')->constrained('dining_tables')->nullOnDelete();
            $table->string('table_name', 30)->nullable()->after('table_id'); // snapshot untuk struk & riwayat
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_id');
            $table->dropColumn('table_name');
        });
        Schema::dropIfExists('dining_tables');
    }
};
