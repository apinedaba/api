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
    Schema::create('consultas_contacto', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');          
        $table->string('email');           
        $table->string('telefono');        
        $table->string('tipo_sesion');     
        $table->text('motivo');           
        $table->timestamps();              
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultas_contacto');
    }
};
