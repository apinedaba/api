<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_packages', function (Blueprint $table) {
            $table->enum('promotion_discount_type', ['percent', 'fixed'])->nullable()->after('package_total_price');
            $table->decimal('promotion_discount_value', 10, 2)->nullable()->after('promotion_discount_type');
            $table->dateTime('promotion_starts_at')->nullable()->after('promotion_discount_value');
            $table->dateTime('promotion_ends_at')->nullable()->after('promotion_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('session_packages', function (Blueprint $table) {
            $table->dropColumn([
                'promotion_discount_type',
                'promotion_discount_value',
                'promotion_starts_at',
                'promotion_ends_at',
            ]);
        });
    }
};
