<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_id')->unique(); // ID de la suscripción (sub_...)
            $table->string('stripe_plan'); // El Price ID del plan (price_...)
            $table->string('stripe_status'); // ej. 'active', 'canceled', 'past_due'
            $table->timestamp('ends_at')->nullable(); // Fecha de finalización si se cancela
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
