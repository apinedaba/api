<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('is_enabled')->default(true);
            $table->string('custom_title')->nullable();
            $table->text('custom_description')->nullable();
            $table->decimal('custom_price', 10, 2)->nullable();
            $table->string('custom_currency', 10)->default('MXN');
            $table->string('custom_therapy_type', 120)->nullable();
            $table->string('custom_certification', 160)->nullable();
            $table->text('custom_image_url')->nullable();
            $table->text('custom_public_url')->nullable();
            $table->string('custom_schedule_summary')->nullable();
            $table->string('custom_availability', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_catalog_items');
    }
};
