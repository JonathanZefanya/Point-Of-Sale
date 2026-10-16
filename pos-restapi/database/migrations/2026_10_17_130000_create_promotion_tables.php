<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20);                       // App\Enums\PromotionType
            $table->unsignedBigInteger('value')->default(0);  // persen (0–100) atau rupiah, tergantung type
            $table->json('rules_json')->nullable();           // produk/kategori, minimum belanja, buy X get Y, jadwal happy hour
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('outlet_ids')->nullable();           // null = semua outlet
            $table->boolean('requires_voucher')->default(false); // hanya berlaku dengan kode voucher
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('promotion_id')->constrained();
            $table->unsignedInteger('quota')->nullable();     // null = tidak terbatas
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('customer_id')->constrained();
            $table->unsignedBigInteger('manual_discount')->default(0)->after('discount_total');
            $table->json('promotions_json')->nullable()->after('manual_discount'); // snapshot promo yang dipakai
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_id', 'manual_discount', 'promotions_json']);
        });
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('promotions');
    }
};
