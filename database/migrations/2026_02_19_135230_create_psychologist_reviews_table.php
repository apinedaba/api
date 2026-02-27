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
        Schema::create('psychologist_reviews', function (Blueprint $table) {
            $table->foreignId('psychologist_id')->constrained('users')->onDelete('cascade'); // assuming psychologists are in `users`
            $table->string('name');
            $table->string('email');
            $table->string('email_hash', 64);
            $table->string('device_id')->nullable();
            $table->smallInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('approved')->default(true);
            $table->unique(['psychologist_id', 'email_hash']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psychologist_reviews');
    }
};
