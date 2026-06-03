<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minder_groups', function (Blueprint $table) {
            // Marca si el grupo es un mensaje directo 1-a-1 entre dos psicólogos.
            // Los DMs se crean únicamente via POST /minder/dm, nunca por el endpoint store.
            $table->boolean('is_dm')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('minder_groups', function (Blueprint $table) {
            $table->dropColumn('is_dm');
        });
    }
};
