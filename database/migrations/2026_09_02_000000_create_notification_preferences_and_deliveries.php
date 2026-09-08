<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notifiable');
            $table->string('event_key', 100);
            $table->json('channels')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone', 64)->default('America/Mexico_City');
            $table->timestamps();
            $table->unique(['notifiable_type', 'notifiable_id', 'event_key'], 'notification_preferences_recipient_event_unique');
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable();
            $table->nullableMorphs('notifiable');
            $table->string('event_key', 100)->index();
            $table->string('channel', 30)->index();
            $table->string('status', 30)->default('sent')->index();
            $table->string('idempotency_key', 190)->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['idempotency_key', 'channel'], 'notification_deliveries_idempotency_channel_unique');
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notification_deliveries_recipient_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
    }
};
