<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('MXN');
            $table->string('status')->default('requested')->index();
            $table->string('stripe_connect_account_id')->nullable()->index();
            $table->string('stripe_transfer_id')->nullable()->index();
            $table->string('stripe_payout_id')->nullable()->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('payout_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('professional_withdrawal_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_withdrawal_id');
            $table->unsignedBigInteger('payment_id');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index(['payment_id', 'professional_withdrawal_id'], 'withdrawal_payment_lookup');
            $table->index('professional_withdrawal_id', 'withdrawal_payment_withdrawal_idx');
            $table->index('payment_id', 'withdrawal_payment_payment_idx');
            $table->foreign('professional_withdrawal_id', 'withdrawal_payment_withdrawal_fk')
                ->references('id')
                ->on('professional_withdrawals')
                ->cascadeOnDelete();
            $table->foreign('payment_id', 'withdrawal_payment_payment_fk')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_withdrawal_payments');
        Schema::dropIfExists('professional_withdrawals');
    }
};
