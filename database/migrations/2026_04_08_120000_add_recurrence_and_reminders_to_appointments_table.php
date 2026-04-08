<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'recurrence_id')) {
                $table->uuid('recurrence_id')->nullable()->after('google_event_id');
                $table->index('recurrence_id');
            }

            if (!Schema::hasColumn('appointments', 'recurrence_frequency')) {
                $table->string('recurrence_frequency', 20)->nullable()->after('recurrence_id');
            }

            if (!Schema::hasColumn('appointments', 'recurrence_interval')) {
                $table->unsignedInteger('recurrence_interval')->nullable()->after('recurrence_frequency');
            }

            if (!Schema::hasColumn('appointments', 'recurrence_until')) {
                $table->date('recurrence_until')->nullable()->after('recurrence_interval');
            }

            if (!Schema::hasColumn('appointments', 'recurrence_position')) {
                $table->unsignedInteger('recurrence_position')->nullable()->after('recurrence_until');
            }

            if (!Schema::hasColumn('appointments', 'synced_with_google')) {
                $table->boolean('synced_with_google')->default(false)->after('recurrence_position');
            }

            if (!Schema::hasColumn('appointments', 'notification_meta')) {
                $table->json('notification_meta')->nullable()->after('extendedProps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'notification_meta')) {
                $table->dropColumn('notification_meta');
            }

            if (Schema::hasColumn('appointments', 'synced_with_google')) {
                $table->dropColumn('synced_with_google');
            }

            if (Schema::hasColumn('appointments', 'recurrence_position')) {
                $table->dropColumn('recurrence_position');
            }

            if (Schema::hasColumn('appointments', 'recurrence_until')) {
                $table->dropColumn('recurrence_until');
            }

            if (Schema::hasColumn('appointments', 'recurrence_interval')) {
                $table->dropColumn('recurrence_interval');
            }

            if (Schema::hasColumn('appointments', 'recurrence_frequency')) {
                $table->dropColumn('recurrence_frequency');
            }

            if (Schema::hasColumn('appointments', 'recurrence_id')) {
                $table->dropIndex(['recurrence_id']);
                $table->dropColumn('recurrence_id');
            }
        });
    }
};
