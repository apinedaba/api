<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_expired_at')->nullable();
            $table->timestamp('trial_reminder_day_1_at')->nullable();
            $table->timestamp('trial_reminder_day_3_at')->nullable();
            $table->timestamp('trial_reminder_day_7_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('trial_expired_at');
            $table->dropColumn('trial_reminder_day_1_at');
            $table->dropColumn('trial_reminder_day_3_at');
            $table->dropColumn('trial_reminder_day_7_at');
        });
    }
};
