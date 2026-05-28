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
        Schema::create('content_sections', function (Blueprint $table) {
            $table->id();

            // Identificador único de la sección (home, menu, footer, etc.)
            $table->string('key')->unique()->comment('Clave única: home, menu, footer...');

            // Datos JSON de la sección
            $table->json('data')->comment('Contenido JSON de la sección');

            // Control de versiones
            $table->integer('version')->default(1)->comment('Número de versión para rollback');

            // Auditoría - referencias a Administrator
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

            // Índices para performance
            $table->index('key');
            $table->index('updated_at');
        });

        // Tabla de historial de versiones
        Schema::create('content_section_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_section_id')
                ->constrained('content_sections')
                ->onDelete('cascade');
            $table->integer('version_number');
            $table->json('data')->comment('Datos de la versión anterior');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('administrators')
                ->onDelete('set null');
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->unique(['content_section_id', 'version_number'], 'cs_version_unique');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_section_versions');
        Schema::dropIfExists('content_sections');
    }
};
