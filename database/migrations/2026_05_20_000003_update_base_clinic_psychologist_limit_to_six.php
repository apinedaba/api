<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clinics')
            ->where('base_psychologist_limit', 5)
            ->update(['base_psychologist_limit' => 6]);
    }

    public function down(): void
    {
        DB::table('clinics')
            ->where('base_psychologist_limit', 6)
            ->update(['base_psychologist_limit' => 5]);
    }
};
