<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'identity_verification_status')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY identity_verification_status VARCHAR(40) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN identity_verification_status TYPE VARCHAR(40) USING identity_verification_status::text');
            DB::statement("ALTER TABLE users ALTER COLUMN identity_verification_status SET DEFAULT 'pending'");
        }
        // SQLite no restringe los valores de una columna VARCHAR/ENUM emulada.
    }

    public function down(): void
    {
        // Intencionalmente no se restaura el ENUM: podría truncar estados futuros.
    }
};
