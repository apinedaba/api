<?php

use App\Models\ClinicMembership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_users', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('patient')->constrained('clinics')->nullOnDelete();
            $table->index(['clinic_id', 'user']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('patient')->constrained('clinics')->nullOnDelete();
            $table->index(['clinic_id', 'user', 'start']);
        });

        if (class_exists(ClinicMembership::class)) {
            $primaryMemberships = ClinicMembership::query()
                ->where('is_primary', true)
                ->get()
                ->groupBy('user_id');

            if ($primaryMemberships->isNotEmpty()) {
                foreach ($primaryMemberships as $userId => $memberships) {
                    $clinicId = optional($memberships->first())->clinic_id;
                    if (!$clinicId) {
                        continue;
                    }

                    DB::table('patient_users')
                        ->where('user', $userId)
                        ->whereNull('clinic_id')
                        ->update(['clinic_id' => $clinicId]);

                    DB::table('appointments')
                        ->where('user', $userId)
                        ->whereNull('clinic_id')
                        ->update(['clinic_id' => $clinicId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
        });

        Schema::table('patient_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
        });
    }
};
