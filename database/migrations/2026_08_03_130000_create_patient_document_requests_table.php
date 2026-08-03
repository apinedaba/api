<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('template_id', 100)->nullable();
            $table->string('public_token', 100)->unique();
            $table->string('title', 160);
            $table->longText('content');
            $table->boolean('requires_signature')->default(true);
            $table->longText('professional_signature_data_url')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('signer_name', 160)->nullable();
            $table->string('signer_role', 100)->nullable();
            $table->longText('signature_data_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_document_requests');
    }
};
