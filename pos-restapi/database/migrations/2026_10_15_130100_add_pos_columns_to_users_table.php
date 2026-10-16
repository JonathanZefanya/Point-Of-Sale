<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('password'); // hash PIN 6 digit untuk login kasir
            $table->string('role', 20)->default('cashier')->after('pin')->index();
            $table->boolean('is_active')->default(true)->after('role');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['role']);
            $table->dropColumn(['pin', 'role', 'is_active']);
        });
    }
};
