<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequestRequest;
use App\Http\Requests\UpdateAppointmentRequestRequest;
use App\Models\AppointmentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AppointmentRequestController extends Controller
{
    /**
     * POST /appointment-requests
     *
     * Crea una nueva solicitud de cita.
     * Usada desde el dashboard del paciente/usuario.
     */
    public function store(StoreAppointmentRequestRequest $request): JsonResponse
    {
        $appointmentRequest = AppointmentRequest::create($request->validated());

        return response()->json(
            $appointmentRequest->load(['patient', 'psychologist']),
            201
        );
    }

    /**
     * PATCH /appointment-requests/{id}
     *
     * Actualiza el estado de una solicitud (approved / rejected).
     * Solo el psicólogo dueño de la solicitud puede modificarla.
     */
    public function update(UpdateAppointmentRequestRequest $request, int $id): JsonResponse
    {
        $appointmentRequest = AppointmentRequest::findOrFail($id);

        // Verificar que la solicitud pertenece al psicólogo autenticado.
        if ($appointmentRequest->psychologist_id !== auth()->id()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $appointmentRequest->update($request->validated());

        return response()->json(
            $appointmentRequest->fresh()->load(['patient', 'psychologist']),
            200
        );
    }

    /**
     * GET /psychologists/{id}/appointment-requests
     *
     * Devuelve todas las solicitudes pendientes de un psicólogo específico,
     * ordenadas de más reciente a más antigua.
     */
    public function indexByPsychologist(int $id): JsonResponse
    {
        // Verificamos que el psicólogo exista antes de consultar.
        $psychologist = User::findOrFail($id);

        $requests = AppointmentRequest::with('patient')
            ->where('psychologist_id', $psychologist->id)
            ->pending()
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        return response()->json($requests, 200);
    }
}
