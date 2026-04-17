<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->foreignId('discount_coupon_id')->nullable()->after('package_session_count')->constrained('discount_coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('discount_coupon_id');
            $table->string('coupon_discount_type', 20)->nullable()->after('coupon_code');
            $table->decimal('coupon_discount_value', 10, 2)->nullable()->after('coupon_discount_type');
            $table->decimal('subtotal_amount', 10, 2)->nullable()->after('coupon_discount_value');
            $table->decimal('coupon_discount_amount', 10, 2)->nullable()->after('subtotal_amount');
            $table->decimal('final_amount', 10, 2)->nullable()->after('coupon_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->dropForeign(['discount_coupon_id']);
            $table->dropColumn([
                'discount_coupon_id',
                'coupon_code',
                'coupon_discount_type',
                'coupon_discount_value',
                'subtotal_amount',
                'coupon_discount_amount',
                'final_amount',
            ]);
        });
    }
};
