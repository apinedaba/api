<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contract_files', function (Blueprint $t) {
      $t->uuid('id')->primary();
      $t->uuid('contract_instance_id');
      $t->foreign('contract_instance_id')->references('id')->on('contract_instances')->cascadeOnDelete();
      $t->enum('type',['original_pdf','patient_signed_upload','supplement']);
      $t->string('file_public_id');
      $t->string('file_url');
      $t->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
      $t->timestamp('uploaded_at');
      $t->text('notes')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('contract_files'); }
};