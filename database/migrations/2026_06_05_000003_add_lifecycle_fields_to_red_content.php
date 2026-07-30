<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('red_preguntas', function (Blueprint $table) {
            $table->enum('status', ['open', 'closed'])->default('open')->after('is_active');
            $table->enum('close_reason', ['resolved', 'duplicate', 'outdated', 'other'])->nullable()->after('status');
            $table->string('close_note', 500)->nullable()->after('close_reason');
            $table->timestamp('closed_at')->nullable()->after('close_note');
            $table->timestamp('edited_at')->nullable()->after('closed_at');
        });

        Schema::table('red_respuestas', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('is_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('red_respuestas', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('red_preguntas', function (Blueprint $table) {
            $table->dropColumn(['status', 'close_reason', 'close_note', 'closed_at', 'edited_at']);
        });
    }
};
