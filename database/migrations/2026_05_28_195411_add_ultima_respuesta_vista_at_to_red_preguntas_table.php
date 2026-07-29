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
        Schema::table('red_preguntas', function (Blueprint $table) {
            $table->timestamp('ultima_respuesta_vista_at')->nullable()->after('mejor_respuesta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('red_preguntas', function (Blueprint $table) {
            $table->dropColumn('ultima_respuesta_vista_at');
        });
    }
};
