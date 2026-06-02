<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->json('target_audience')->nullable();
            $table->json('locations')->nullable();
            // pending_payment | paid | active | finished
            $table->string('status', 20)->default('pending_payment');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['group_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_requests');
    }
};
