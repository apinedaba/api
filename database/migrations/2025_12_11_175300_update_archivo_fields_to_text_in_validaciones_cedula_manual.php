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
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            // Cambiar de string a text para soportar URLs largas de Cloudinary
            $table->text('archivo_cedula')->nullable()->change();
            $table->text('archivo_titulo')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validaciones_cedula_manual', function (Blueprint $table) {
            // Revertir a string
            $table->string('archivo_cedula')->nullable()->change();
            $table->string('archivo_titulo')->nullable()->change();
        });
    }
};
