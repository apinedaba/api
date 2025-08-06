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
        // Usamos Schema::table() para modificar una tabla existente
        Schema::table('appointment_carts', function (Blueprint $table) {
            // Agregamos la nueva columna 'formato'
            // La hacemos nullable() para evitar errores con los registros que ya existen.
            // Usamos after() para decidir en qué parte de la tabla aparecerá.
            $table->string('formato')->nullable()->after('tipoSesion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El método down debe revertir lo que hicimos en el método up
        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->dropColumn('formato');
        });
    }
};
