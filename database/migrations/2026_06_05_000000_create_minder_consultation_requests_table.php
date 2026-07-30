<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_consultation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('red_pregunta_id')->nullable()->constrained('red_preguntas')->nullOnDelete();
            $table->foreignId('red_respuesta_id')->nullable()->constrained('red_respuestas')->nullOnDelete();
            $table->foreignId('minder_group_id')->nullable()->constrained('minder_groups')->nullOnDelete();
            $table->string('subject', 160);
            $table->text('message');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->index(['sender_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minder_consultation_requests');
    }
};
