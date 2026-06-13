<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_question_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_id')->constrained('red_preguntas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_saved')->default(false);
            $table->boolean('is_following')->default(false);
            $table->timestamps();

            $table->unique(['pregunta_id', 'user_id']);
            $table->index(['user_id', 'is_saved']);
            $table->index(['user_id', 'is_following']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_question_preferences');
    }
};
