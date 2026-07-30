<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->json('psychometric_scales')->nullable()->after('observations');
            $table->json('mental_exam')->nullable()->after('psychometric_scales');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['psychometric_scales', 'mental_exam']);
        });
    }
};
