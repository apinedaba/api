<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_patient_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('recipient', 40);
            $table->string('title', 180);
            $table->longText('content');
            $table->json('included_sections');
            $table->text('instructions')->nullable();
            $table->string('model', 80)->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_patient_summaries');
    }
};
