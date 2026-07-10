<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_referrals', function (Blueprint $table) {
            $table->string('reward_mode')->nullable()->after('status');
            $table->index(['referrer_user_id', 'status', 'reward_mode'], 'pr_referrals_reward_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::table('professional_referrals', function (Blueprint $table) {
            $table->dropIndex('pr_referrals_reward_mode_idx');
            $table->dropColumn('reward_mode');
        });
    }
};
