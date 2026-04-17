<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 80)->nullable();
            $table->string('status', 30)->default('trial');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('first_activated_at')->nullable();
            $table->timestamp('last_status_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['vendedor_id', 'status']);
            $table->index(['first_activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_referrals');
    }
};
