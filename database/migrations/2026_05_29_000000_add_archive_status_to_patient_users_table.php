<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_users', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->string('status_before_archive', 100)->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('patient_users', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'status_before_archive']);
        });
    }
};
