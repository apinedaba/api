<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $templates = [
            [
                'key' => 'membership_payment_failed',
                'template_name' => 'pago_fallido',
                'description' => 'Notifica al psicologo que fallo el pago de su membresia.',
                'body_parameters' => ['professional_public_name'],
            ],
            [
                'key' => 'appointment_rescheduled',
                'template_name' => 'cita_reprogramada',
                'description' => 'Notifica al paciente la nueva fecha de su cita.',
                'body_parameters' => ['patient_name', 'new_date', 'professional_public_name'],
            ],
            [
                'key' => 'appointment_session_reminder',
                'template_name' => 'cita_recordatorio',
                'description' => 'Recordatorio 30 minutos antes para paciente y psicologo.',
                'body_parameters' => ['counterpart_name', 'session_time'],
            ],
        ];

        foreach ($templates as $template) {
            DB::table('whatsapp_templates')->updateOrInsert(
                ['key' => $template['key']],
                [
                    'template_name' => $template['template_name'],
                    'language' => 'es_MX',
                    'category' => 'appointments',
                    'description' => $template['description'],
                    'body_parameters' => json_encode($template['body_parameters']),
                    'buttons' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('whatsapp_templates')->whereIn('key', [
            'membership_payment_failed',
            'appointment_rescheduled',
            'appointment_session_reminder',
        ])->delete();
    }
};
