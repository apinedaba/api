<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_support_settings', function (Blueprint $table) {
            $table->id();
            $table->string('support_email')->default('mindmeetmx@gmail.com');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('minimum_notice_hours')->default(24);
            $table->unsignedSmallInteger('booking_window_days')->default(21);
            $table->json('weekly_availability');
            $table->timestamps();
        });

        Schema::create('minder_support_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic', 60);
            $table->text('description');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->string('meeting_url', 500)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minder_support_appointments');
        Schema::dropIfExists('minder_support_settings');
    }
};
