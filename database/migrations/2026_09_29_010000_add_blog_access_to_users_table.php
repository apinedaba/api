<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_publish_blog')->default(false)->after('activo')->index();
            $table->timestamp('blog_access_enabled_at')->nullable()->after('can_publish_blog');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['can_publish_blog']);
            $table->dropColumn(['can_publish_blog', 'blog_access_enabled_at']);
        });
    }
};
