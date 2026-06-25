<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('user_id')->constrained('patients')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->after('patient_id')->constrained('appointments')->nullOnDelete();
            $table->timestamp('viewed_at')->nullable()->after('appointment_id');
            $table->timestamp('contacted_at')->nullable()->after('viewed_at');
            $table->timestamp('converted_at')->nullable()->after('contacted_at');
            $table->timestamp('discarded_at')->nullable()->after('converted_at');
            $table->text('notes')->nullable()->after('discarded_at');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'email']);
        });

        DB::table('consultas_contacto')
            ->whereNull('status')
            ->orWhere('status', 'created')
            ->update(['status' => 'new']);
    }

    public function down(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['user_id', 'email']);
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['appointment_id']);
            $table->dropColumn([
                'patient_id',
                'appointment_id',
                'viewed_at',
                'contacted_at',
                'converted_at',
                'discarded_at',
                'notes',
            ]);
        });
    }
};
