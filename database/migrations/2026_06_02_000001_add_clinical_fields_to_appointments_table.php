<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'objective')) {
                $table->text('objective')->nullable()->after('comments');
            }
            if (!Schema::hasColumn('appointments', 'session_description')) {
                $table->text('session_description')->nullable()->after('objective');
            }
            if (!Schema::hasColumn('appointments', 'pre_session_note')) {
                $table->text('pre_session_note')->nullable()->after('session_description');
            }
            if (!Schema::hasColumn('appointments', 'interventions')) {
                $table->text('interventions')->nullable()->after('pre_session_note');
            }
            if (!Schema::hasColumn('appointments', 'action_plan')) {
                $table->text('action_plan')->nullable()->after('interventions');
            }
            if (!Schema::hasColumn('appointments', 'observations')) {
                $table->text('observations')->nullable()->after('action_plan');
            }
            if (!Schema::hasColumn('appointments', 'payment_status')) {
                $table->string('payment_status', 30)->nullable()->after('observations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            foreach (['payment_status', 'observations', 'action_plan', 'interventions', 'pre_session_note', 'session_description', 'objective'] as $column) {
                if (Schema::hasColumn('appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
