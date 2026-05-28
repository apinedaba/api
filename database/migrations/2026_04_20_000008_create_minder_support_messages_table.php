<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('minder_support_threads')->onDelete('cascade');
            $table->morphs('sender');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minder_support_messages');
    }
};
