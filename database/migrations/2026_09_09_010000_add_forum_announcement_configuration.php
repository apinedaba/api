<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindmeet_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('whatsapp_templates')->updateOrInsert(
            ['key' => 'new_forum_question'],
            [
                'template_name' => 'nuevo_foro',
                'language' => 'es_MX',
                'category' => 'community',
                'description' => 'Anuncia una nueva publicación destacada de Mentes en Red.',
                'body_parameters' => json_encode(['question_title']),
                'buttons' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('whatsapp_notification_rules')->updateOrInsert(
            ['event_key' => 'new_forum_question'],
            [
                'label' => 'Nueva publicación en Mentes en Red',
                'description' => 'Difusión masiva de una publicación autorizada a psicólogos activos.',
                'channels' => json_encode(['whatsapp']),
                'whatsapp_template_key' => 'new_forum_question',
                'recipient' => 'professional',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('whatsapp_notification_rules')->where('event_key', 'new_forum_question')->delete();
        DB::table('whatsapp_templates')->where('key', 'new_forum_question')->delete();
        Schema::dropIfExists('mindmeet_settings');
    }
};
