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
        Schema::create('failed_webhook_events', function (Blueprint $table) {
            $table->id();

            // Tipo de evento (ej. 'checkout.session.completed')
            $table->string('event_type');

            // ID de Stripe session o evento
            $table->string('stripe_id')->nullable();

            // Datos crudos del webhook
            $table->longText('payload');

            // Información del error
            $table->text('error_message');
            $table->longText('error_trace')->nullable();

            // Control de retry
            $table->integer('attempt_count')->default(1);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Índices para query rápido
            $table->index('event_type');
            $table->index('stripe_id');
            $table->index('resolved');
            $table->index('next_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_webhook_events');
    }
};
