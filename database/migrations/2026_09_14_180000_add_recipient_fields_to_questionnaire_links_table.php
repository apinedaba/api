<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_links', function (Blueprint $table) {
            // Una invitación puede enviarse antes de que exista el expediente.
            $table->foreignId('patient')->nullable()->change();
            $table->string('recipient_name', 120)->nullable()->after('patient');
            $table->string('recipient_email')->nullable()->after('recipient_name');
            $table->index('recipient_email');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_links', function (Blueprint $table) {
            $table->dropIndex(['recipient_email']);
            $table->dropColumn(['recipient_name', 'recipient_email']);
            $table->foreignId('patient')->nullable(false)->change();
        });
    }
};
