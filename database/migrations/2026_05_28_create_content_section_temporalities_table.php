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
        Schema::create('content_section_temporalities', function (Blueprint $table) {
            $table->id();

            // Relación con content_sections
            $table->foreignId('content_section_id')
                ->constrained('content_sections')
                ->onDelete('cascade');

            // Identifiers
            $table->string('name')->comment('Nombre de la temporalidad (ej: Hotsale Mayo)');
            $table->string('slug')->unique()->comment('Slug para identificar única (ej: hotsale-mayo)');

            // Contenido
            $table->json('data')->comment('Datos JSON completos de esta temporalidad');

            // Estado
            $table->boolean('is_active')->default(false)->comment('¿Esta temporalidad está activa ahora?');
            $table->boolean('is_programmed')->default(false)->comment('¿Tiene rango de fechas programado?');

            // Fechas de vigencia (si es programada)
            $table->dateTime('start_date')->nullable()->comment('Fecha inicio de vigencia');
            $table->dateTime('end_date')->nullable()->comment('Fecha fin de vigencia');

            // Notas
            $table->text('notes')->nullable()->comment('Notas sobre esta temporalidad');

            // Auditoría
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('administrators')
                ->onDelete('set null');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('administrators')
                ->onDelete('set null');

            // Timestamps
            $table->timestamps();

            // Índices
            $table->index('content_section_id');
            $table->index('is_active');
            $table->index('is_programmed');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_section_temporalities');
    }
};
