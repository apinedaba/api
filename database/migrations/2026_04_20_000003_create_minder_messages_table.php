<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('minder_groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('body');
            $table->foreignId('parent_id')->nullable()->constrained('minder_messages')->onDelete('cascade');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->index(['group_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minder_messages');
    }
};
