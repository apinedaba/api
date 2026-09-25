<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_coupon_user', function (Blueprint $table) {
            $table->foreignId('discount_coupon_id')->constrained('discount_coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['discount_coupon_id', 'user_id']);
            $table->index(['user_id', 'discount_coupon_id']);
        });

        DB::table('discount_coupons')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(500, function ($coupons) {
                $now = now();
                DB::table('discount_coupon_user')->insertOrIgnore(
                    $coupons->map(fn ($coupon) => [
                        'discount_coupon_id' => $coupon->id,
                        'user_id' => $coupon->user_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });

        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->foreignId('discount_coupon_id')->nullable()->after('user_id')->constrained('discount_coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('discount_coupon_id');
            $table->string('coupon_discount_type', 20)->nullable()->after('coupon_code');
            $table->decimal('coupon_discount_value', 10, 2)->nullable()->after('coupon_discount_type');
            $table->decimal('coupon_discount_amount', 10, 2)->nullable()->after('coupon_discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->dropForeign(['discount_coupon_id']);
            $table->dropColumn([
                'discount_coupon_id', 'coupon_code', 'coupon_discount_type',
                'coupon_discount_value', 'coupon_discount_amount',
            ]);
        });

        Schema::dropIfExists('discount_coupon_user');
    }
};
