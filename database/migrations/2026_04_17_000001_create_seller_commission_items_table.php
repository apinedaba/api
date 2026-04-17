<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_commission_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_referral_id')->constrained('seller_referrals')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->constrained('vendedores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('milestone', 40);
            $table->decimal('amount', 10, 2);
            $table->string('status', 30)->default('pending');
            $table->date('eligible_at');
            $table->date('cut_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['seller_referral_id', 'milestone'], 'seller_commission_referral_milestone_unique');
            $table->index(['vendedor_id', 'status', 'cut_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_commission_items');
    }
};
