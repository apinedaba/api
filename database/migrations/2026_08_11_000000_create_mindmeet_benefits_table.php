<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindmeet_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('partner_name')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('image_url')->nullable();
            $table->string('image_public_id')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('contact_label')->nullable();
            $table->string('contact_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'mm_benefits_active_order_idx');
            $table->index(['starts_at', 'ends_at'], 'mm_benefits_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mindmeet_benefits');
    }
};
