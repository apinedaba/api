<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('role', 30)->default('participant');
            $table->string('attendance_status', 30)->default('expected');
            $table->string('consent_status', 30)->default('pending');
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamps();

            $table->unique(['appointment_id', 'patient_id']);
            $table->index(['patient_id', 'attendance_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_participants');
    }
};
