<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_support_google_accounts', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->unsignedInteger('expires_in');
            $table->string('calendar_id')->default('primary');
            $table->timestamps();
        });

        Schema::table('minder_support_appointments', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('meeting_url');
            $table->string('google_calendar_id')->nullable()->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('minder_support_appointments', function (Blueprint $table) {
            $table->dropColumn(['google_event_id', 'google_calendar_id']);
        });

        Schema::dropIfExists('minder_support_google_accounts');
    }
};
