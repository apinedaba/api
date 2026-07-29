<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_exercise_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('mode', 40)->default('activities');
            $table->string('model', 80)->nullable();
            $table->json('request_payload');
            $table->json('response_payload');
            $table->json('token_usage')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_exercise_generations');
    }
};
