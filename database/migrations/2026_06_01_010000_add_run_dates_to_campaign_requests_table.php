<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_requests', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('status');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('campaign_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'starts_at', 'ends_at']);
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
