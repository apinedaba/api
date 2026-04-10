<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('session_count');
            $table->decimal('base_session_price', 10, 2);
            $table->decimal('package_session_price', 10, 2);
            $table->decimal('package_total_price', 10, 2);
            $table->string('currency', 10)->default('MXN');
            $table->string('formato', 30)->nullable();
            $table->string('tipo_sesion', 100)->nullable();
            $table->unsignedTinyInteger('duracion')->nullable();
            $table->json('categoria')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_packages');
    }
};
