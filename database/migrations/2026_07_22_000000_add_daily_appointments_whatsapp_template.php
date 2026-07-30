<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('whatsapp_templates')->updateOrInsert(
            ['key' => 'daily_appointments'],
            [
                'template_name' => 'citas_hoy',
                'language' => 'es_MX',
                'category' => 'appointments',
                'description' => 'Resumen diario de citas para el psicologo.',
                'body_parameters' => json_encode(['appointment_count', 'patient_schedule']),
                'buttons' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('whatsapp_templates')->where('key', 'daily_appointments')->delete();
    }
};
