<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_verification_code_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_code', 6)->nullable()->after('email_verified_at');
            $table->timestamp('code_expires_at')->nullable()->after('verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verification_code', 'code_expires_at']);
        });
    }
};
