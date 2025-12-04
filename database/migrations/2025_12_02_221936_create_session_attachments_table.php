<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('session_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('appointments')->onDelete('cascade');
            $table->string('filename');
            $table->string('url');
            $table->string('public_id');
            $table->string('extension', 20)->nullable();
            $table->integer('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_attachments');
    }
};
