<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('registration_source', 30)
                ->default('professional')
                ->after('organization_id')
                ->index();
        });

        // Antes de esta columna, el alta profesional creaba el vínculo en el mismo
        // flujo. Un paciente sin vínculo, o vinculado después, corresponde al portal.
        DB::table('patients')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($patients) {
                $firstConnections = DB::table('patient_users')
                    ->whereIn('patient', $patients->pluck('id'))
                    ->selectRaw('patient, MIN(created_at) as connected_at')
                    ->groupBy('patient')
                    ->pluck('connected_at', 'patient');

                $websitePatientIds = $patients
                    ->filter(function ($patient) use ($firstConnections) {
                        $connectedAt = $firstConnections->get($patient->id);

                        if (!$connectedAt) {
                            return true;
                        }

                        return Carbon::parse($connectedAt)
                            ->greaterThan(Carbon::parse($patient->created_at)->addMinutes(15));
                    })
                    ->pluck('id');

                if ($websitePatientIds->isNotEmpty()) {
                    DB::table('patients')
                        ->whereIn('id', $websitePatientIds)
                        ->update(['registration_source' => 'website']);
                }
            });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['registration_source']);
            $table->dropColumn('registration_source');
        });
    }
};
