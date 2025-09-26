<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->enum('payer_type', ['patient', 'minder']);
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->onDelete('set null');
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('MXN');
            $table->string('payment_method');
            $table->string('status')->default('pending');
            $table->string('stripe_payment_id')->nullable();
            $table->string('receipt_url')->nullable();
            $table->string('motivo_devolucion')->nullable();
            $table->string('fecha_devolucion')->nullable();
            $table->string('id_transaccion_reembolsada')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};