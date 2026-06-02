<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_package_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_slots')->default(0);
            // recruiting | full | active | completed
            $table->string('status', 20)->default('recruiting');
            $table->timestamps();

            $table->index(['marketing_package_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_campaigns');
    }
};
