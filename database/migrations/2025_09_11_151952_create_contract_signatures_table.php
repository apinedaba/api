<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contract_signatures', function (Blueprint $t) {
      $t->uuid('id')->primary();
      $t->uuid('contract_instance_id');
      $t->foreign('contract_instance_id')->references('id')->on('contract_instances')->cascadeOnDelete();
      $t->enum('signer_type',['patient','professional'])->default('patient');
      $t->string('signature_public_id');
      $t->string('signature_url');
      $t->timestamp('signed_at');
      $t->string('signer_ip')->nullable();
      $t->text('signer_ua')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('contract_signatures'); }
};