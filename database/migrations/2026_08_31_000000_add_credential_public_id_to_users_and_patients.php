<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['users', 'patients'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('credential_public_id', 32)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'patients'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['credential_public_id']);
                $table->dropColumn('credential_public_id');
            });
        }
    }
};
