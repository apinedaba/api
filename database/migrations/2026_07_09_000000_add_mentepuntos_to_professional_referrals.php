<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_referral_codes', function (Blueprint $table) {
            $table->string('reward_preference')->default('free_months')->after('status');
        });

        Schema::create('professional_referral_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('points_enabled')->default(false);
            $table->unsignedInteger('points_per_qualified_referral')->default(10);
            $table->string('points_name')->default('MindPoints');
            $table->text('points_description')->nullable();
            $table->timestamps();
        });

        Schema::create('professional_referral_point_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->integer('balance_points')->default(0);
            $table->integer('lifetime_earned_points')->default(0);
            $table->integer('lifetime_redeemed_points')->default(0);
            $table->timestamps();

            $table->foreign('user_id', 'pr_point_accounts_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id', 'pr_point_accounts_user_unique');
        });

        Schema::create('professional_referral_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('professional_referral_id')->nullable();
            $table->string('type');
            $table->integer('points');
            $table->string('status')->default('posted');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'pr_point_tx_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('professional_referral_id', 'pr_point_tx_referral_fk')->references('id')->on('professional_referrals')->nullOnDelete();
            $table->index(['user_id', 'type'], 'pr_point_tx_user_type_idx');
            $table->unique(['professional_referral_id', 'type'], 'pr_point_tx_referral_type_unique');
        });

        DB::table('professional_referral_settings')->insert([
            'points_enabled' => false,
            'points_per_qualified_referral' => 10,
            'points_name' => 'MindPoints',
            'points_description' => 'Saldo virtual para canjear por beneficios MindMeet. Cada MindPoint equivale a $1 MXN de valor referencial.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_referral_point_transactions');
        Schema::dropIfExists('professional_referral_point_accounts');
        Schema::dropIfExists('professional_referral_settings');

        Schema::table('professional_referral_codes', function (Blueprint $table) {
            $table->dropColumn('reward_preference');
        });
    }
};
