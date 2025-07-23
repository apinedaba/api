<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cedula_selfie_url')->nullable();
            $table->string('ine_selfie_url')->nullable();
            $table->enum('identity_verification_status', ['pending', 'approved', 'rejected'])->default('pending');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cedula_selfie_url');
            $table->dropColumn('ine_selfie_url');
            $table->dropColumn('identity_verification_status');
        });
    }
};
