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
        Schema::create('sintomas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psicologo_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('paciente_id')->constrained('patients')->onDelete('cascade');
            $table->json('sintoma');
            $table->dateTime('fecha');
            $table->timestamps();
    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sintomas');
    }
};
