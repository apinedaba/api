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
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('escalas')->nullable();
            $table->json('linea_vida')->nullable();
            $table->json('examen_mental')->nullable();
            $table->longText('diagnostico')->nullable();
            $table->longText('plan_tratamiento')->nullable();
            $table->longText('dinamicaFamiliar')->nullable();
            $table->longText('vidaSocial')->nullable();
            $table->string('firma')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
