<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_administrative_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->string('action', 40);
            $table->string('previous_status', 80)->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->string('refund_currency', 8)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->text('notification_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_administrative_actions');
    }
};
