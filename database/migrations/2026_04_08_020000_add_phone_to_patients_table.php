<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
                $table->index('phone');
            }
        });

        DB::table('patients')
            ->orderBy('id')
            ->get(['id', 'contacto'])
            ->each(function ($patient) {
                $contacto = json_decode($patient->contacto ?? '[]', true);
                $telefono = preg_replace('/\D+/', '', data_get($contacto, 'telefono', '')) ?: null;

                if ($telefono) {
                    if (strlen($telefono) === 12 && str_starts_with($telefono, '52')) {
                        $telefono = substr($telefono, -10);
                    }

                    DB::table('patients')
                        ->where('id', $patient->id)
                        ->update(['phone' => $telefono]);
                }
            });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE patients MODIFY email VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'phone')) {
                $table->dropIndex(['phone']);
                $table->dropColumn('phone');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE patients MODIFY email VARCHAR(255) NOT NULL');
        }
    }
};
