<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_notification_rules', function (Blueprint $table) {
            $table->string('recipient', 30)->default('patient')->after('whatsapp_template_key');
        });

        DB::table('whatsapp_notification_rules')
            ->where('event_key', 'appointment_created')
            ->update(['recipient' => 'professional']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_notification_rules', function (Blueprint $table) {
            $table->dropColumn('recipient');
        });
    }
};
