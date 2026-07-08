<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('professional_referral_rewards');
        Schema::dropIfExists('professional_referral_reward_rules');
        Schema::dropIfExists('professional_referrals');
        Schema::dropIfExists('professional_referral_codes');

        Schema::create('professional_referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('code');
            $table->string('status')->default('active');
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'pr_codes_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('code', 'pr_codes_code_unique');
            $table->unique('user_id', 'pr_codes_user_unique');
        });

        Schema::create('professional_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id');
            $table->foreignId('referred_user_id');
            $table->foreignId('professional_referral_code_id')->nullable();
            $table->string('code')->nullable();
            $table->string('status')->default('registered');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('first_paid_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_status_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('referrer_user_id', 'pr_ref_referrer_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('referred_user_id', 'pr_ref_referred_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('professional_referral_code_id', 'pr_ref_code_fk')->references('id')->on('professional_referral_codes')->nullOnDelete();
            $table->unique('referred_user_id', 'pr_ref_referred_unique');
            $table->index('code', 'pr_ref_code_idx');
            $table->index('status', 'pr_ref_status_idx');
            $table->index(['referrer_user_id', 'status'], 'pr_ref_referrer_status_idx');
        });

        Schema::create('professional_referral_reward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('required_qualified_referrals');
            $table->string('reward_type')->default('free_months');
            $table->unsignedInteger('reward_months')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('professional_referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id');
            $table->foreignId('professional_referral_reward_rule_id')->nullable();
            $table->string('milestone_key');
            $table->unsignedInteger('required_qualified_referrals');
            $table->string('reward_type')->default('free_months');
            $table->unsignedInteger('reward_months')->default(1);
            $table->string('status')->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('referrer_user_id', 'pr_rewards_referrer_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('professional_referral_reward_rule_id', 'pr_rewards_rule_fk')->references('id')->on('professional_referral_reward_rules')->nullOnDelete();
            $table->index('status', 'pr_rewards_status_idx');
            $table->unique(['referrer_user_id', 'milestone_key'], 'pr_rewards_referrer_milestone_unique');
        });

        DB::table('professional_referral_reward_rules')->insert([
            [
                'name' => '1 mes gratis',
                'required_qualified_referrals' => 3,
                'reward_type' => 'free_months',
                'reward_months' => 1,
                'is_active' => true,
                'sort_order' => 10,
                'description' => 'Recompensa inicial por referir 3 psicólogos con membresía activa.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '2 meses gratis',
                'required_qualified_referrals' => 5,
                'reward_type' => 'free_months',
                'reward_months' => 2,
                'is_active' => true,
                'sort_order' => 20,
                'description' => 'Recompensa por llegar a 5 psicólogos con membresía activa.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '1 año gratis',
                'required_qualified_referrals' => 10,
                'reward_type' => 'free_months',
                'reward_months' => 12,
                'is_active' => true,
                'sort_order' => 30,
                'description' => 'Recompensa anual por llegar a 10 psicólogos con membresía activa.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_referral_rewards');
        Schema::dropIfExists('professional_referral_reward_rules');
        Schema::dropIfExists('professional_referrals');
        Schema::dropIfExists('professional_referral_codes');
    }
};
