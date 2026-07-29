<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['question', 'answer']);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 80);
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->enum('resolution_action', ['none', 'hidden', 'restored'])->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index(['status', 'created_at']);
            $table->index(['reported_by', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_reports');
    }
};
