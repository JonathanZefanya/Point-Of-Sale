<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable()->index(); // dinormalisasi: 0812xxxx
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('group', 20)->default('member');  // App\Enums\CustomerGroup
            $table->unsignedBigInteger('points')->default(0); // loyalty (2.4)
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // sales.customer_id sudah ada sejak BE-1.7 — sekarang tabelnya ada, pasang FK
        Schema::table('sales', fn (Blueprint $table) => $table->foreign('customer_id')->references('id')->on('customers'));
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign(['customer_id']));
        Schema::dropIfExists('customers');
    }
};
