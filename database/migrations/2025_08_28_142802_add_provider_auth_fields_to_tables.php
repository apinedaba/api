<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('id');
            $table->string('provider_id')->nullable()->after('provider_name');
            $table->string('avatar')->nullable()->after('email');
            $table->string('password')->nullable()->change();
            $table->unique(['provider_name', 'provider_id']);
        });

        // --- Para la tabla de Pacientes (patients) ---
        Schema::table('patients', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('id');
            $table->string('provider_id')->nullable()->after('provider_name');
            $table->string('avatar')->nullable()->after('email');
            $table->string('password')->nullable()->change();
            $table->unique(['provider_name', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['provider_name', 'provider_id']);
            $table->dropColumn(['provider_name', 'provider_id', 'avatar']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['provider_name', 'provider_id']);
            $table->dropColumn(['provider_name', 'provider_id', 'avatar']);
        });
    }
};
