<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('device_tokens', 'notifiable_type')) {
                $table->nullableMorphs('notifiable');
            }
        });

        DB::table('device_tokens')
            ->whereNull('notifiable_id')
            ->whereNotNull('user_id')
            ->update([
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id' => DB::raw('user_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('device_tokens', 'notifiable_type')) {
                $table->dropMorphs('notifiable');
            }
        });
    }
};
