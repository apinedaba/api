<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('type', ['public', 'private'])->default('public');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_members')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->text('rules')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minder_groups');
    }
};
