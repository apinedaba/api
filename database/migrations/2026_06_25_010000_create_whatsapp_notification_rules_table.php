<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('channels')->nullable();
            $table->string('whatsapp_template_key')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('whatsapp_notification_rules')->insert([
            [
                'event_key' => 'appointment_created',
                'label' => 'Sesion creada',
                'description' => 'Cuando se agenda una sesion nueva para un paciente.',
                'channels' => json_encode(['email', 'database', 'whatsapp']),
                'whatsapp_template_key' => 'appointment_created',
                'email_subject' => 'MindMeet | Sesion programada',
                'email_body' => null,
                'sms_body' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'appointment_reminder',
                'label' => 'Recordatorio de sesion',
                'description' => 'Recordatorio previo a la sesion.',
                'channels' => json_encode(['email', 'database', 'whatsapp']),
                'whatsapp_template_key' => 'appointment_reminder',
                'email_subject' => 'MindMeet | Recordatorio de sesion',
                'email_body' => null,
                'sms_body' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'appointment_cancelled',
                'label' => 'Sesion cancelada',
                'description' => 'Cuando una sesion se cancela.',
                'channels' => json_encode(['email', 'database', 'whatsapp']),
                'whatsapp_template_key' => 'appointment_cancelled',
                'email_subject' => 'MindMeet | Sesion cancelada',
                'email_body' => null,
                'sms_body' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'patient_invitation',
                'label' => 'Invitacion de paciente',
                'description' => 'Cuando se invita a un paciente a MindMeet.',
                'channels' => json_encode(['email', 'database', 'whatsapp']),
                'whatsapp_template_key' => 'patient_invitation',
                'email_subject' => 'MindMeet | Invitacion',
                'email_body' => null,
                'sms_body' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_rules');
    }
};
