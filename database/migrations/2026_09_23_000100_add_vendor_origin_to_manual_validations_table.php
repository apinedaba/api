<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            $table->string('origen', 40)->nullable()->after('estado');
            $table->unsignedBigInteger('vendedor_externo_id')->nullable()->after('origen');
            $table->index('vendedor_externo_id');
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            $table->dropIndex(['vendedor_externo_id']);
            $table->dropColumn(['origen', 'vendedor_externo_id']);
        });
    }
};
