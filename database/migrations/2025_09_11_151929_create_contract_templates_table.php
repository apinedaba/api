<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contract_templates', function (Blueprint $t) {
      $t->uuid('id')->primary();
      $t->enum('owner_type', ['system','user'])->default('system');
      $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
      $t->string('title');
      $t->longText('html');
      $t->boolean('editable')->default(false);
      $t->json('tags_schema')->nullable();
      $t->unsignedInteger('version')->default(1);
      $t->boolean('is_active')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('contract_templates'); }
};