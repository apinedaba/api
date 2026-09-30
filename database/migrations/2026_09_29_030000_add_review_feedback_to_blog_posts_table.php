<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->text('review_feedback')->nullable()->after('reviewed_at');
            $table->timestamp('changes_requested_at')->nullable()->after('review_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['review_feedback', 'changes_requested_at']);
        });
    }
};
