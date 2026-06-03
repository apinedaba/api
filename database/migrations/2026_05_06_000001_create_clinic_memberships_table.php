<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 50)->default('psychologist');
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_manage_schedule')->default(false);
            $table->boolean('can_manage_patients')->default(false);
            $table->boolean('can_view_finance')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_memberships');
    }
};
