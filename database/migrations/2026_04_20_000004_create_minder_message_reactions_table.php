<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minder_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('minder_messages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('emoji', 10);
            $table->timestamps();
            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minder_message_reactions');
    }
};
