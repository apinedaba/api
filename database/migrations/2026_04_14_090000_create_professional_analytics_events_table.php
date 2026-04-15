<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consulta_contacto_id')->nullable()->constrained('consultas_contacto')->nullOnDelete();
            $table->string('event_type', 60)->index();
            $table->string('source', 80)->nullable()->index();
            $table->string('medium', 80)->nullable();
            $table->string('campaign', 160)->nullable();
            $table->string('landing_page', 160)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->string('session_id', 120)->nullable()->index();
            $table->string('ip_hash', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type', 'created_at'], 'pae_user_event_created_idx');
            $table->index(['user_id', 'source', 'created_at'], 'pae_user_source_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_analytics_events');
    }
};
