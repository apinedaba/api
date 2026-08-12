<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_analytics_events', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('consulta_contacto_id')->constrained('appointments')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->after('appointment_id')->constrained('payments')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->after('payment_id')->constrained('patients')->nullOnDelete();
            $table->string('event_key', 190)->nullable()->after('event_type')->unique();
            $table->index(['user_id', 'patient_id', 'created_at'], 'pae_user_patient_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('professional_analytics_events', function (Blueprint $table) {
            $table->dropIndex('pae_user_patient_created_idx');
            $table->dropForeign(['appointment_id']);
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['patient_id']);
            $table->dropUnique(['event_key']);
            $table->dropColumn(['appointment_id', 'payment_id', 'patient_id', 'event_key']);
        });
    }
};
