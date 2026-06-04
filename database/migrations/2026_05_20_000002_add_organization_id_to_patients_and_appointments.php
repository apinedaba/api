<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();
                $table->index(['organization_id', 'email']);
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();
                $table->index(['organization_id', 'user', 'start']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'organization_id')) {
                $table->dropIndex(['organization_id', 'user', 'start']);
                $table->dropConstrainedForeignId('organization_id');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'organization_id')) {
                $table->dropIndex(['organization_id', 'email']);
                $table->dropConstrainedForeignId('organization_id');
            }
        });
    }
};
