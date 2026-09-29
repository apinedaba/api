<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('whatsapp_notification_rules')->updateOrInsert(
            ['event_key' => 'appointment_patient_response'],
            [
                'label' => 'Respuesta del paciente a una cita',
                'description' => 'Avisa al psicólogo cuando el paciente confirma, cancela o solicita reprogramar.',
                // Se habilita WhatsApp desde la automatización cuando ya exista
                // una plantilla Meta aprobada para este evento.
                'channels' => json_encode(['database', 'email']),
                'whatsapp_template_key' => null,
                'recipient' => 'professional',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('whatsapp_notification_rules')
            ->where('event_key', 'appointment_patient_response')
            ->delete();
    }
};
