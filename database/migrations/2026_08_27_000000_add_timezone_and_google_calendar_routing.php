<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Mexico_City')->after('horarios');
        });

        Schema::table('google_accounts', function (Blueprint $table) {
            $table->string('default_calendar_id')->nullable()->after('expires_in');
            $table->json('calendar_sync_rules')->nullable()->after('default_calendar_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('google_calendar_id')->nullable()->after('google_event_id');
        });

        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->string('patient_timezone', 64)->nullable()->after('hora');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_carts', fn (Blueprint $table) => $table->dropColumn('patient_timezone'));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('google_calendar_id'));
        Schema::table('google_accounts', fn (Blueprint $table) => $table->dropColumn(['default_calendar_id', 'calendar_sync_rules']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('timezone'));
    }
};
