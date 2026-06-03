<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE appointment_carts MODIFY estado ENUM('pendiente', 'pendientePago', 'pagado', 'expirado') NOT NULL DEFAULT 'pendiente'"
        );
    }

    public function down(): void
    {
        DB::table('appointment_carts')
            ->where('estado', 'pendientePago')
            ->update(['estado' => 'pendiente']);

        DB::statement(
            "ALTER TABLE appointment_carts MODIFY estado ENUM('pendiente', 'pagado', 'expirado') NOT NULL DEFAULT 'pendiente'"
        );
    }
};
