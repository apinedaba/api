<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            // Las solicitudes históricas se conservan con NULL; cada nueva
            // solicitud tendrá una llave única derivada de su cédula.
            $table->string('request_key', 64)->nullable()->unique()->after('numero_cedula');
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropColumn('request_key');
        });
    }
};
