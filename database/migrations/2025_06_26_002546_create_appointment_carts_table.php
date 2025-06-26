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
        Schema::create('appointment_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users'); // el profesional
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('tipoSesion');
            $table->string('duracion');
            $table->integer('precio');
            $table->enum('estado', ['pendiente', 'pagado', 'expirado'])->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_carts');
    }
};
