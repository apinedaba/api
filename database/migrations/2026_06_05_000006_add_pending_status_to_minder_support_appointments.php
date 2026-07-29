<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE minder_support_appointments MODIFY status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE minder_support_appointments SET status = 'confirmed' WHERE status = 'pending'");
        DB::statement("ALTER TABLE minder_support_appointments MODIFY status ENUM('confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'confirmed'");
    }
};
