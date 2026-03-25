Copiar

<?php
// ══════════════════════════════════════════════════════════════════
// MIGRACIÓN
// Archivo: database/migrations/xxxx_create_documentacion_favoritos_table.php
// Ejecutar: php artisan make:migration create_documentacion_favoritos_table
//           (luego reemplaza el contenido con este)
// ══════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentacion_favoritos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psicologo_id');
            $table->string('drive_id');             // ID del archivo en Google Drive
            $table->timestamps();

            // Un psicólogo solo puede marcar el mismo documento una vez
            $table->unique(['psicologo_id', 'drive_id']);

            $table->foreign('psicologo_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentacion_favoritos');
    }
};
