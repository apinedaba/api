<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('template_name');
            $table->string('language', 20)->default('es_MX');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->json('body_parameters')->nullable();
            $table->json('buttons')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('whatsapp_templates')->insert([
            [
                'key' => 'appointment_created',
                'template_name' => 'confirm_session',
                'language' => 'es_MX',
                'category' => 'appointments',
                'description' => 'Confirmacion de asistencia para una cita nueva.',
                'body_parameters' => json_encode(['patient_name', 'date', 'time', 'professional_name']),
                'buttons' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'appointment_reminder',
                'template_name' => 'confirm_session',
                'language' => 'es_MX',
                'category' => 'appointments',
                'description' => 'Recordatorio de sesion con botones de confirmacion.',
                'body_parameters' => json_encode(['patient_name', 'date', 'time', 'professional_name']),
                'buttons' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'appointment_cancelled',
                'template_name' => 'confirm_session',
                'language' => 'es_MX',
                'category' => 'appointments',
                'description' => 'Template configurable para cambios o cancelaciones de sesion.',
                'body_parameters' => json_encode(['patient_name', 'date', 'time', 'professional_name']),
                'buttons' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'patient_invitation',
                'template_name' => 'patient_invitation',
                'language' => 'es_MX',
                'category' => 'patients',
                'description' => 'Invitacion inicial de paciente.',
                'body_parameters' => json_encode(['patient_name', 'url']),
                'buttons' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
