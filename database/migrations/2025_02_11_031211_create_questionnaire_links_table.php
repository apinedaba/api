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
        Schema::create('questionnaire_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->onDelete('cascade'); // Relación con el cuestionario
            $table->string('token')->unique(); // Token único para el enlace
            $table->string('status', 100)->nullable()->default('pending');
            $table->timestamp('expires_at')->nullable(); // Fecha de expiración del enlace
            $table->foreignId('user')->constrained()->cascadeOnDelete();
            $table->foreignId('patient')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questionnaire_links');
    }
};
