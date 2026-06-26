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
        $this->abortIfArchived($session, $psychologist);

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
        $psychologist = auth()->user();
        $this->authorizeSession($session, $psychologist);
        $this->abortIfArchived($session, $psychologist);

        $request->validate([
            'url' => 'required|string',
            'public_id' => 'required|string',
            'filename' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:20',
            'size' => 'nullable|integer|min:0',
        ]);

        $filename = $this->sanitizeAttachmentFilename(
            $request->input('filename') ?: basename(parse_url($request->url, PHP_URL_PATH) ?: $request->url)
        );
        $extension = $request->input('extension') ?: pathinfo($filename, PATHINFO_EXTENSION);

        $attachment = SessionAttachment::create([
            'session_id' => $session->id,
            'filename' => $filename,
            'url' => $request->url,
            'public_id' => $request->public_id,
            'extension' => strtolower($extension ?: ''),
            'size' => $request->input('size'),
        ]);

        return response()->json($attachment);
    }

    public function deleteAttachment($id)
    {
        $attachment = SessionAttachment::findOrFail($id);
        $this->authorizeAttachment($attachment, auth()->user());

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
        $this->authorizeAttachment($attachment, auth()->user());

        // URL de Cloudinary
        $url = $attachment->url;

        // Descargar el archivo desde Cloudinary
        $response = Http::get($url);

        if ($response->failed()) {
            return response()->json(['error' => 'No se pudo cargar el archivo'], 400);
        }

        $contentType = $response->header('Content-Type') ?: $this->mimeTypeForExtension($attachment->extension);
        $filename = str_replace('"', '', $attachment->display_name ?: 'archivo');

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
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

    private function abortIfArchived($session, $psychologist): void
    {
        $isArchived = PatientUser::where('patient', $session->patient)
            ->where('user', $psychologist->id)
            ->whereNotNull('archived_at')
            ->exists();

        if ($isArchived) {
            abort(423, 'Paciente archivado. Reactivalo para modificar su expediente.');
        }
    }

    private function authorizeAttachment(SessionAttachment $attachment, $psychologist): void
    {
        $session = $attachment->session;

        if (!$session) {
            abort(404, 'Sesion no encontrada.');
        }

        $this->authorizeSession($session, $psychologist);
    }

    private function mimeTypeForExtension(?string $extension): string
    {
        return match (strtolower($extension ?? '')) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function sanitizeAttachmentFilename(string $filename): string
    {
        $filename = trim(basename(str_replace('\\', '/', $filename)));
        $filename = preg_replace('/[\r\n"]+/', '', $filename) ?: 'archivo';

        return mb_substr($filename, 0, 255);
    }
}
