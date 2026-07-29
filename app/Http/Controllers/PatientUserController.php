<?php

namespace App\Http\Controllers;

use App\Models\PatientUser;
use App\Models\Patient;
use App\Models\Appointment;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;


class PatientUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        return response()->json(data: PatientUser::with('patient')->with('expediente')->where('user', $user->id)->get(), status: 200);
    }

    public function archive($patient): JsonResponse
    {
        $relation = $this->resolveRelation($patient);

        if (!$relation) {
            return response()->json([
                'message' => 'Paciente no encontrado en tu directorio',
                'type' => 'error',
            ], 404);
        }

        if (!$relation->archived_at) {
            $relation->update([
                'activo' => false,
                'status_before_archive' => $relation->status,
                'status' => 'Archivado',
                'archived_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Paciente archivado correctamente',
            'type' => 'success',
            'data' => $relation->fresh(['patient', 'expediente']),
        ], 200);
    }

    public function reactivate($patient): JsonResponse
    {
        $relation = $this->resolveRelation($patient);

        if (!$relation) {
            return response()->json([
                'message' => 'Paciente no encontrado en tu directorio',
                'type' => 'error',
            ], 404);
        }

        $relation->update([
            'activo' => true,
            'status' => $relation->status_before_archive ?: 'Vinculado',
            'status_before_archive' => null,
            'archived_at' => null,
        ]);

        return response()->json([
            'message' => 'Paciente reactivado correctamente',
            'type' => 'success',
            'data' => $relation->fresh(['patient', 'expediente']),
        ], 200);
    }

    public function activateManually($patient): JsonResponse
    {
        $relation = $this->resolveRelation($patient);

        if (!$relation) {
            return response()->json([
                'message' => 'Paciente no encontrado en tu directorio',
                'type' => 'error',
            ], 404);
        }

        if ($relation->archived_at) {
            return response()->json([
                'message' => 'Paciente archivado. Reactivalo antes de activar el vinculo.',
                'type' => 'error',
            ], 423);
        }

        if (!$relation->activo) {
            $relation->update([
                'activo' => true,
                'status' => 'Activado por psicologo',
                'video_call_room' => $relation->video_call_room ?: 'mindmeet-room-' . Str::uuid(),
            ]);
        }

        return response()->json([
            'message' => 'Paciente activado correctamente',
            'type' => 'success',
            'data' => $relation->fresh(['patient', 'expediente']),
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    public function enlacePacienteProfesional($patient)
    {
        $user = Auth::user();
        $checkExist = PatientUser::where('user', $user->id)->where('patient', $patient)->first();
        if (isset($checkExist->id)) {
            return [
                'rasson' => "Ya existe un paciente en tu lista con estos datos",
                'message' => "Usuario existente",
                'type' => "error"
            ];
        }

        $enlace = PatientUser::create([
            'user' => $user->id,
            'patient' => $patient,
            'activo' => true,
            'status' => 'Vinculado',
            'video_call_room' => 'mindmeet-room-' . Str::uuid(),
        ]);

        if ($enlace) {
            return $enlace;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PatientUser $patientUser)
    {
        return $patientUser->first();
    }

    public function getCurrentProfesional()
    {
        $user = Auth::user();
        if (!$user->id) {
            return response()->json([
                'rasson' => "Tenemos problemas con tu usuario",
                'message' => "Problema de usuario no encontrado",
                'type' => "error"
            ]);
        }
        $currentRelation = PatientUser::where('patient', $user->id)
            ->where('activo', true)
            ->whereNull('archived_at')
            ->with('user')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(1)
            ->get();

        if ($currentRelation->isEmpty()) {
            $nextAppointment = Appointment::query()
                ->with('user')
                ->where('patient', $user->id)
                ->where('start', '>=', now())
                ->orderBy('start')
                ->first();

            if ($nextAppointment?->user) {
                return response()->json(data: [[
                    'id' => 'appointment-'.$nextAppointment->id,
                    'user' => $nextAppointment->user,
                    'patient' => $user->id,
                    'activo' => true,
                    'status' => 'Cita programada',
                    'archived_at' => null,
                    'video_call_room' => $nextAppointment->video_call_room,
                ]], status: 200);
            }
        }

        return response()->json(data: $currentRelation, status: 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PatientUser $patientUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $enlace = PatientUser::where('user', $user->id)->where('patient', $request->id);
        if ($enlace->first()?->archived_at && !$request->boolean('reactivate_archived')) {
            return response()->json([
                'message' => 'Paciente archivado. Usa la reactivacion para habilitarlo de nuevo.',
                'type' => 'error',
            ], 423);
        }
        $currentActive = $enlace->first()['activo'];
        if (isset($request->isPatient) && !$currentActive) {
            $enlace->update(["activo" => !$currentActive, 'status' => "Enlace Aceptado"]);
            $patient = Patient::where('id', $request->id)->first();
            EmailService::send(
                $patient->email,
                'Ahora tu cuenta de minsmeet esta completa.',
                'email.cuenta-activada-paciente',
                [
                    'name' => $patient->name,
                    'url' => rtrim(config('app.perfil_paciente_url') ?: 'https://paciente.mindmeet.com.mx', '/') . '/iniciar-sesion'
                ]
            );
            $response = [
                'message' => 'Paciente activado con exito',
                'status' => 'ok',
                'rasson' => "Haz aceptado con exito la peticion de tu psicologo, espera a que te de indicaciones de tu proxima cita, no es necesario que permanezcas en esta pagina",
                'type' => "success"
            ];
            return response()->json($response, 200);
        }
        if (isset($request->isPatient) && $currentActive) {
            $response = [
                'status' => 'ok',
                'message' => 'Paciente activado con exito',
                'rasson' => "Haz aceptado con exito la peticion de tu psicologo, espera a que te de indicaciones de tu proxima cita, no es necesario que permanezcas en esta pagina",
                'type' => "success"
            ];
            return response()->json($response, 200);
        }

        $currentActive = !$currentActive;
        $enlace->update(["activo" => $currentActive]);
        $currentActive = $currentActive === 1 ? "desactivado" : "activado";
        $response = [
            'rasson' => 'El usuario se a ' . $currentActive . ' correctamente',
            'message' => "Usuario $currentActive",
            'type' => "success"
        ];
        return response()->json($response, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PatientUser $patientUser)
    {
        //
    }

    private function resolveRelation($patient): ?PatientUser
    {
        $user = Auth::user();

        return PatientUser::where('user', $user->id)
            ->where('patient', $patient)
            ->first();
    }
}
