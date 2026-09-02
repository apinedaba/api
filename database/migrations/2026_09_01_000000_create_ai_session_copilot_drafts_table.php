<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_session_copilot_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('mode', 30);
            $table->string('status', 20)->default('draft');
            $table->json('input_payload')->nullable();
            $table->json('output_payload');
            $table->string('model')->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'mode', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_session_copilot_drafts');
    }
};
