<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('professional_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('referral_code_id')->constrained('professional_referral_codes')->restrictOnDelete();
            $table->string('status', 30)->default('registered');
            $table->timestamp('registered_at');
            $table->timestamp('first_paid_at')->nullable();
            $table->string('first_paid_invoice_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['inviter_user_id', 'status']);
        });

        Schema::create('professional_referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_referral_id')->unique()->constrained('professional_referrals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(30);
            $table->string('currency', 3)->default('MXN');
            $table->string('status', 30)->default('credited');
            $table->timestamp('credited_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('source_reference')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_referral_rewards');
        Schema::dropIfExists('professional_referrals');
        Schema::dropIfExists('professional_referral_codes');
    }
};
