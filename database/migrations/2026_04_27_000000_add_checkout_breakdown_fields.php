<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->decimal('session_base_amount', 10, 2)->nullable()->after('precio');
            $table->decimal('charge_subtotal_amount', 10, 2)->nullable()->after('session_base_amount');
            $table->decimal('platform_fee_rate', 6, 4)->nullable()->after('charge_subtotal_amount');
            $table->decimal('platform_fee_amount', 10, 2)->nullable()->after('platform_fee_rate');
            $table->decimal('total_charge_amount', 10, 2)->nullable()->after('platform_fee_amount');
            $table->decimal('psychologist_amount', 10, 2)->nullable()->after('total_charge_amount');
            $table->decimal('remaining_balance_amount', 10, 2)->nullable()->after('psychologist_amount');
            $table->string('charge_mode')->nullable()->after('remaining_balance_amount');
            $table->string('payout_status')->nullable()->after('charge_mode');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('session_base_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('charge_subtotal_amount', 10, 2)->nullable()->after('session_base_amount');
            $table->decimal('platform_fee_rate', 6, 4)->nullable()->after('charge_subtotal_amount');
            $table->decimal('platform_fee_amount', 10, 2)->nullable()->after('platform_fee_rate');
            $table->decimal('total_charge_amount', 10, 2)->nullable()->after('platform_fee_amount');
            $table->decimal('psychologist_amount', 10, 2)->nullable()->after('total_charge_amount');
            $table->decimal('remaining_balance_amount', 10, 2)->nullable()->after('psychologist_amount');
            $table->string('charge_mode')->nullable()->after('remaining_balance_amount');
            $table->string('payout_status')->nullable()->after('charge_mode');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->dropColumn([
                'session_base_amount',
                'charge_subtotal_amount',
                'platform_fee_rate',
                'platform_fee_amount',
                'total_charge_amount',
                'psychologist_amount',
                'remaining_balance_amount',
                'charge_mode',
                'payout_status',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'session_base_amount',
                'charge_subtotal_amount',
                'platform_fee_rate',
                'platform_fee_amount',
                'total_charge_amount',
                'psychologist_amount',
                'remaining_balance_amount',
                'charge_mode',
                'payout_status',
            ]);
        });
    }
};
