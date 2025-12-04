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
        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('psychologist_id')->constrained('users')->onDelete('cascade');
            $table->longText('content')->nullable();
            $table->boolean('is_important')->default(false);
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->enum('type', [
                'post_sesion',
                'pre_sesion',
                'adicional',
                'riesgo',
                'administrativa'
            ])->default('post_sesion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_notes');
    }
};
