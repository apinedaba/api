<?php

namespace App\Services;

use App\Models\PatientUser;
use App\Models\User;
use Illuminate\Support\Str;

class AppointmentService
{
    /**
     * Verifica si existe la relación profesional-paciente.
     * Si no existe, la crea con sala única.
     * Si existe pero no tiene sala, la genera.
     */
    public function ensureRelationshipAndRoom($userId, $patientId, $clinicId = null)
    {
        $clinicId = $clinicId ?: $this->resolvePrimaryClinicId($userId);

        $relation = PatientUser::where([
            'user' => $userId,
            'patient' => $patientId,
        ])->first();

        if (!$relation) {
            $relation = PatientUser::create([
                'user' => $userId,
                'patient' => $patientId,
                'clinic_id' => $clinicId,
                'activo' => true,
                'status' => 'Vinculado',
                'video_call_room' => 'mindmeet-room-' . Str::uuid(),
            ]);
        } elseif (!$relation->video_call_room) {
            $relation->video_call_room = 'mindmeet-room-' . Str::uuid();
            if (!$relation->clinic_id && $clinicId) {
                $relation->clinic_id = $clinicId;
            }
            $relation->save();
        } elseif (!$relation->clinic_id && $clinicId) {
            $relation->clinic_id = $clinicId;
            $relation->save();
        }

        return $relation;
    }

    protected function resolvePrimaryClinicId($userId): ?int
    {
        $user = User::with('primaryClinicMembership')->find($userId);

        return $user?->primaryClinicMembership?->clinic_id;
    }
}
