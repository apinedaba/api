<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('professional_withdrawal_id')
                ->nullable()
                ->constrained('professional_withdrawals')
                ->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('direction')->nullable()->index();
            $table->string('stripe_object_type')->nullable();
            $table->string('stripe_object_id')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('payload')->nullable();
            $table->json('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_transaction_logs');
    }
};
