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
        Schema::table('psychologist_reviews', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->foreignId('patient_id')->nullable()->change();
            $table->foreign('patient_id')->references('id')->on('users')->nullOnDelete();
            $table->string('name')->change();
            $table->string('email')->change();
            $table->string('email_hash', 64)->change();
            $table->string('device_id')->nullable()->change();
            $table->smallInteger('rating')->change();
            $table->text('comment')->nullable()->change();
            $table->boolean('approved')->default(true)->change();
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
