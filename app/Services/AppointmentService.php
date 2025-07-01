<?php

namespace App\Services;

use App\Models\PatientUser;
use Illuminate\Support\Str;

class AppointmentService
{
    /**
     * Verifica si existe la relación profesional-paciente.
     * Si no existe, la crea con sala única.
     * Si existe pero no tiene sala, la genera.
     */
    public function ensureRelationshipAndRoom($userId, $patientId)
    {
        $relation = PatientUser::where([
            'user' => $userId,
            'patient' => $patientId,
        ])->first();

        if (!$relation) {
            $relation = PatientUser::create([
                'user' => $userId,
                'patient' => $patientId,
                'activo' => true,
                'status' => 'Vinculado',
                'video_call_room' => 'mindmeet-room-' . Str::uuid(),
            ]);
        } elseif (!$relation->video_call_room) {
            $relation->video_call_room = 'mindmeet-room-' . Str::uuid();
            $relation->save();
        }

        return $relation;
    }
}
