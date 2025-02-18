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
        Schema::create('questionnaires_link_responses', function (Blueprint $table) {
            $table->id();
            $table->json('response')->nullable();
            $table->foreignId('questionnaire_link_id')->constrained()->onDelete('cascade'); // Relación con el cuestionario            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questionnaires_link_responses');
    }
};
