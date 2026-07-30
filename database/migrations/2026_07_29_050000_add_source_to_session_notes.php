<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            $table->string('source', 20)->default('written')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
