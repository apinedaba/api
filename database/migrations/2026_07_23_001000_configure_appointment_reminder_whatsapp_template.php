<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('whatsapp_templates')
            ->where('key', 'appointment_reminder')
            ->update([
                'template_name' => 'confirma_tu_cita',
                'language' => 'es_MX',
                'body_parameters' => json_encode(['patient_name']),
                // El payload incluye el ID de la cita y se construye en tiempo de envio.
                'buttons' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('whatsapp_templates')
            ->where('key', 'appointment_reminder')
            ->update([
                'template_name' => 'confirm_session',
                'language' => 'es_MX',
                'body_parameters' => json_encode(['patient_name', 'date', 'time', 'professional_name']),
                'buttons' => null,
                'updated_at' => now(),
            ]);
    }
};
