<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('author_user_id')->nullable()->after('author_name')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_review_at')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_for_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_user_id');
            $table->dropColumn(['submitted_for_review_at', 'reviewed_at']);
        });
    }
};
