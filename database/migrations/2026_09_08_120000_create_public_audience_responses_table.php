<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_audience_responses', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 120)->unique();
            $table->string('audience', 30)->index();
            $table->string('route_choice', 40);
            $table->string('destination', 160);
            $table->string('landing_page', 255)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('utm_source', 100)->nullable()->index();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 160)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['audience', 'route_choice', 'created_at'], 'par_audience_choice_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_audience_responses');
    }
};
