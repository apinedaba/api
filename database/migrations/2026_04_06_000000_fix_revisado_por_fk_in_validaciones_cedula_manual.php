<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->foreign('revisado_por')
                ->references('id')
                ->on('administrators')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->foreign('revisado_por')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }
};
