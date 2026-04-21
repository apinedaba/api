<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_votos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('red_respuestas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['respuesta_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_votos');
    }
};
