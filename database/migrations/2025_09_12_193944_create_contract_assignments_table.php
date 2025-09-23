<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable(); // multi-tenant opcional
            $table->uuid('template_id');
            $table->unsignedBigInteger('patient_id'); // referencia a pacientes/usuarios
            $table->enum('status', ['pending', 'signed'])->default('pending');
            $table->json('payload_json')->nullable(); // valores para {{vars}}
            $table->longText('rendered_html')->nullable(); // HTML listo para firmar
            $table->string('signature_url')->nullable(); // PNG de firma
            $table->string('pdf_url')->nullable(); // PDF final en Cloudinary
            $table->string('signature_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();


            $table->index(['account_id', 'patient_id']);
            $table->index(['account_id', 'template_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('contract_assignments');
    }
};