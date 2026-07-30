<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('appointment_carts', 'source')) {
                $table->string('source')->default('website')->after('appointment_id');
            }

            if (!Schema::hasColumn('appointment_carts', 'stripe_session_id')) {
                $table->string('stripe_session_id')->nullable()->after('payment_intent_id');
            }

            if (!Schema::hasColumn('appointment_carts', 'stripe_payment_status')) {
                $table->string('stripe_payment_status')->nullable()->after('stripe_session_id');
            }
        });

        DB::table('appointment_carts')->where('estado', 'Pagado')->update(['estado' => 'pagado']);
        DB::table('appointment_carts')->where('estado', 'Pendiente')->update(['estado' => 'pendiente']);

        DB::table('appointment_carts')
            ->whereNotNull('appointment_id')
            ->whereNull('payment_intent_id')
            ->whereNull('stripe_session_id')
            ->update(['source' => 'panel']);

        DB::table('appointment_carts')
            ->where(function ($query) {
                $query->whereNotNull('payment_intent_id')
                    ->orWhereNotNull('stripe_session_id')
                    ->orWhereNull('appointment_id');
            })
            ->update(['source' => 'website']);

        DB::table('appointment_carts')
            ->join('payments', 'appointment_carts.appointment_id', '=', 'payments.appointment_id')
            ->whereNotNull('payments.stripe_payment_id')
            ->update([
                'appointment_carts.source' => 'website',
                'appointment_carts.stripe_payment_status' => 'paid',
            ]);

        DB::statement(
            "ALTER TABLE appointment_carts MODIFY estado ENUM('pendiente', 'pendientePago', 'voucher_generado', 'pagado', 'expirado', 'cancelado') NOT NULL DEFAULT 'pendiente'"
        );
    }

    public function down(): void
    {
        DB::table('appointment_carts')
            ->whereIn('estado', ['voucher_generado', 'cancelado'])
            ->update(['estado' => 'expirado']);

        DB::statement(
            "ALTER TABLE appointment_carts MODIFY estado ENUM('pendiente', 'pendientePago', 'pagado', 'expirado') NOT NULL DEFAULT 'pendiente'"
        );

        Schema::table('appointment_carts', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('appointment_carts', 'stripe_payment_status') ? 'stripe_payment_status' : null,
                Schema::hasColumn('appointment_carts', 'stripe_session_id') ? 'stripe_session_id' : null,
                Schema::hasColumn('appointment_carts', 'source') ? 'source' : null,
            ]));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
