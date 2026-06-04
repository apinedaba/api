<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('account_type', 50)->default('clinic');
            $table->string('status', 50)->default('active');
            $table->unsignedInteger('base_psychologist_limit')->default(6);
            $table->unsignedInteger('addon_psychologist_slots')->default(0);
            $table->json('contact')->nullable();
            $table->json('settings')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
