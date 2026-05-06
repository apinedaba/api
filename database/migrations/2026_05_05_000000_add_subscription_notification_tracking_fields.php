<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('upcoming_charge_notified_for_date')->nullable()->after('trial_reminder_day_7_at');
            $table->string('last_payment_failed_invoice_id')->nullable()->after('upcoming_charge_notified_for_date');
            $table->timestamp('last_payment_failed_notified_at')->nullable()->after('last_payment_failed_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'upcoming_charge_notified_for_date',
                'last_payment_failed_invoice_id',
                'last_payment_failed_notified_at',
            ]);
        });
    }
};
