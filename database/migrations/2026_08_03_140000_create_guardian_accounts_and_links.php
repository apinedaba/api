<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guardian_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('guardian_patient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 100);
            $table->boolean('can_manage')->default(true);
            $table->boolean('can_sign')->default(false);
            $table->string('representation_reason', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->unique(['guardian_account_id', 'patient_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('guardian_patient');
        Schema::dropIfExists('guardian_accounts');
    }
};
