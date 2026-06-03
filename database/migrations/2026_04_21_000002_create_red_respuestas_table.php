<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_id')->constrained('red_preguntas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('contenido');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->index(['pregunta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_respuestas');
    }
};
