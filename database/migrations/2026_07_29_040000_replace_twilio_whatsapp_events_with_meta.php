<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'new_lead' => ['Nuevo lead', 'Avisa al psicólogo cuando recibe un contacto desde MindMeet.'],
            'session_payment_registered' => ['Pago de sesión registrado', 'Avisa al psicólogo cuando se registra el pago de una sesión.'],
        ] as $eventKey => [$label, $description]) {
            DB::table('whatsapp_notification_rules')->updateOrInsert(
                ['event_key' => $eventKey],
                [
                    'label' => $label,
                    'description' => $description,
                    'channels' => json_encode(['whatsapp']),
                    'whatsapp_template_key' => null,
                    'recipient' => 'professional',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('whatsapp_notification_rules')
            ->whereIn('event_key', ['new_lead', 'session_payment_registered'])
            ->delete();
    }
};
