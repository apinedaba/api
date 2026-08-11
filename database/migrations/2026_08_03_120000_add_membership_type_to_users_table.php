<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('membership_type', 40)->nullable()->after('has_lifetime_access')->index();
        });

        DB::table('users')
            ->where('has_lifetime_access', true)
            ->whereNull('membership_type')
            ->update(['membership_type' => 'lifetime']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['membership_type']);
            $table->dropColumn('membership_type');
        });
    }
};
