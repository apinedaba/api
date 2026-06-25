<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'public_uuid')) {
                $table->uuid('public_uuid')->nullable()->unique()->after('id');
            }
        });

        DB::table('appointments')
            ->whereNull('public_uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($appointments) {
                foreach ($appointments as $appointment) {
                    DB::table('appointments')
                        ->where('id', $appointment->id)
                        ->update(['public_uuid' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'public_uuid')) {
                $table->dropUnique(['public_uuid']);
                $table->dropColumn('public_uuid');
            }
        });
    }
};
