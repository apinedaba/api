<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('lifecycle_status', 30)->default('scheduled')->after('state')->index();
            $table->timestamp('started_at')->nullable()->after('lifecycle_status');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        DB::table('appointments')
            ->whereIn('state', ['Completada', 'Completado', 'completed', 'Completed'])
            ->update([
                'lifecycle_status' => 'completed',
                'started_at' => DB::raw('start'),
                'completed_at' => DB::raw('end'),
            ]);

        DB::table('appointments')
            ->whereIn('state', ['En curso', 'in_progress'])
            ->update([
                'lifecycle_status' => 'in_progress',
                'started_at' => DB::raw('start'),
            ]);
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['lifecycle_status']);
            $table->dropColumn(['lifecycle_status', 'started_at', 'completed_at']);
        });
    }
};
