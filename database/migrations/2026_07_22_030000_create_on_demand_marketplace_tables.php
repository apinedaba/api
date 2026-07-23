<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('on_demand_professional_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_available')->default(false)->index();
            $table->json('modalities')->nullable();
            $table->decimal('minimum_price', 10, 2)->nullable();
            $table->decimal('maximum_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('response_window_minutes')->default(10);
            $table->timestamp('available_until')->nullable()->index();
            $table->timestamp('next_available_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('on_demand_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('accepted_professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('status', 40)->default('matching')->index();
            $table->string('urgency', 30)->default('today');
            $table->string('modality', 30)->default('online');
            $table->json('specialties')->nullable();
            $table->decimal('maximum_budget', 10, 2)->nullable();
            $table->timestamp('preferred_from')->nullable()->index();
            $table->timestamp('preferred_until')->nullable();
            $table->text('reason')->nullable();
            $table->json('location')->nullable();
            $table->json('safety_screening')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('on_demand_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('on_demand_request_id')->constrained('on_demand_requests')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('match_score', 5, 2)->default(0);
            $table->json('match_reasons')->nullable();
            $table->timestamp('proposed_start')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('responded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['on_demand_request_id', 'professional_id'], 'on_demand_offer_professional_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('on_demand_offers');
        Schema::dropIfExists('on_demand_requests');
        Schema::dropIfExists('on_demand_professional_settings');
    }
};
