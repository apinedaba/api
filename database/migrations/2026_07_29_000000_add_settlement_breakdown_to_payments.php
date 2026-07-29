<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('stripe_fee_amount', 10, 2)->nullable()->after('platform_fee_amount');
            $table->decimal('mindmeet_fee_rate', 6, 4)->nullable()->after('stripe_fee_amount');
            $table->decimal('mindmeet_fee_amount', 10, 2)->nullable()->after('mindmeet_fee_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['stripe_fee_amount', 'mindmeet_fee_rate', 'mindmeet_fee_amount']);
        });
    }
};
