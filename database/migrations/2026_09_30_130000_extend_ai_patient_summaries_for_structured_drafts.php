<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ai_patient_summaries', function (Blueprint $table) {
            $table->string('purpose', 80)->nullable()->after('recipient');
            $table->string('detail_level', 20)->nullable()->after('purpose');
            $table->json('structured_content')->nullable()->after('content');
            $table->string('status', 20)->default('draft')->after('instructions');
        });
    }
    public function down(): void {
        Schema::table('ai_patient_summaries', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'detail_level', 'structured_content', 'status']);
        });
    }
};
