<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contract_instances', function (Blueprint $t) {
      $t->uuid('id')->primary();
      $t->uuid('template_id');
      $t->foreign('template_id')->references('id')->on('contract_templates')->cascadeOnDelete();
      $t->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
      $t->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
      $t->longText('filled_html');
      $t->json('data_snapshot');
      $t->enum('status', ['draft','sent','viewed','signed','rejected','expired','cancelled','uploaded_by_patient'])
        ->default('draft');
      $t->string('signed_pdf_public_id')->nullable();
      $t->string('signed_pdf_url')->nullable();
      $t->string('evidence_hash')->nullable();
      $t->json('evidence_json')->nullable();
      $t->timestamp('expires_at')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('contract_instances'); }
};