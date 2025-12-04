<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\PatientUser;
use App\Models\SessionAttachment;
use App\Models\SessionNote;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PatientTimelineController extends Controller
{
    /**
     * Devuelve el timeline agrupado por sesiones.
     */
    public function index(Request $request, $patientId)
    {
        $psychologist = auth()->user();

        // Verificar que el paciente pertenece al psicólogo
        $patient = PatientUser::where('patient', $patientId)
            ->where('user', $psychologist->id)
            ->with('patient')
            ->firstOrFail();

        // Obtener sesiones + notas + adjuntos
        $sessions = Appointment::where('patient', $patientId)
            ->orderBy('start', 'desc')
            ->with([
                'notes' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                },
                'attachments'
            ])
            ->get();

        return response()->json([
            'patient' => $patient->patient,
            'timeline' => $sessions
        ]);
    }

    /**
     * Crear nueva nota dentro de una sesión.
     */
    public function storeNote(Request $request, $sessionId)
    {
        $session = Appointment::findOrFail($sessionId);
        $psychologist = auth()->user();
        // Verificar acceso
        $this->authorizeSession($session, $psychologist);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'type' => 'required|in:post_sesion,pre_sesion,adicional,riesgo,administrativa'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $note = SessionNote::create([
            'session_id' => $session->id,
            'psychologist_id' => $psychologist->id,
            'content' => $request->content,
            'type' => $request->type,
        ]);

        return response()->json($note);
    }

    /**
     * Subir archivo adjunto.
     */
    public function storeAttachment(Request $request, $sessionId)
    {
        $session = Appointment::findOrFail($sessionId);

        $request->validate([
            'url' => 'required|string',
            'public_id' => 'required|string'
        ]);

        $attachment = SessionAttachment::create([
            'session_id' => $session->id,
            'filename' => basename($request->url),
            'url' => $request->url,
            'public_id' => $request->public_id,
        ]);

        return response()->json($attachment);
    }

    public function deleteAttachment($id)
    {
        $attachment = SessionAttachment::findOrFail($id);

        // Eliminar de Cloudinary
        Cloudinary::destroy($attachment->public_id);

        // Eliminar registro local
        $attachment->delete();

        return response()->json(['message' => 'Eliminar exitoso']);
    }

    /**
     * Eliminar nota
     */
    public function deleteNote($noteId)
    {
        $note = SessionNote::findOrFail($noteId);

        $this->authorizeSession($note->session, auth()->user());

        $note->delete();

        return response()->json(['message' => 'Nota eliminada']);
    }

    public function streamAttachment($id)
    {
        $attachment = SessionAttachment::findOrFail($id);
        // URL de Cloudinary (PDF)
        $url = $attachment->url;

        // Descargar el archivo desde Cloudinary
        $response = Http::get($url);

        if ($response->failed()) {
            return response()->json(['error' => 'No se pudo cargar el archivo'], 400);
        }

        return response($response->body(), 200)
            ->header('Content-Type', 'application/pdf');
    }

    /**
     * Helper: validar acceso del psicólogo a la sesión.
     */
    private function authorizeSession($session, $psychologist)
    {
        if ($session->user !== $psychologist->id) {
            abort(403, 'No autorizado.');
        }
    }
}
