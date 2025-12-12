<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ValidacionCedulaManual;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CedulaCheck extends Controller
{
    /**
     * Método original deshabilitado - Ahora se usa validación manual
     */
    public function buscarCedula(Request $request)
    {
        // Validación de SEP deshabilitada temporalmente
        return response()->json([
            'status' => 'manual_validation_required',
            'message' => 'La validación automática está deshabilitada. Por favor, usa el formulario de validación manual.',
            'requires_manual_data' => true
        ], 200);
    }



    public function getCedulasByUser($userId)
    {
        $cedulas = ValidacionCedulaManual::where('user_id', $userId)->get();
        return response()->json([
            'status' => 'success',
            'data' => $cedulas
        ], 200);
    }

    /**
     * Registrar cédula para validación manual
     */
    public function registrarCedulaManual(Request $request)
    {
        $validated = $request->validate([
            'numero_cedula' => 'required|string|max:20',
            'nombre_completo' => 'required|string|max:255',
            'institucion' => 'required|string|max:255',
            'carrera' => 'required|string|max:255',
            'fecha_expedicion' => 'required|date',
            'archivo_cedula' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'archivo_titulo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $validacion = ValidacionCedulaManual::create([
            'user_id' => auth()->id(),
            'numero_cedula' => $validated['numero_cedula'],
            'nombre_completo' => $validated['nombre_completo'],
            'institucion' => $validated['institucion'],
            'carrera' => $validated['carrera'],
            'fecha_expedicion' => $validated['fecha_expedicion'],
            'estado' => 'pendiente',
        ]);

        // Guardar archivos en Cloudinary si existen
        if ($request->hasFile('archivo_cedula')) {
            $url = $this->uploadToCloudinary($request->file('archivo_cedula'), 'cedulas');
            $validacion->archivo_cedula = $url;
        }

        if ($request->hasFile('archivo_titulo')) {
            $url = $this->uploadToCloudinary($request->file('archivo_titulo'), 'titulos');
            $validacion->archivo_titulo = $url;
        }

        $validacion->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Tu información ha sido enviada correctamente. Validaremos tus datos manualmente en las próximas 24-48 horas.',
            'validacion_id' => $validacion->id,
            'data' => $validacion
        ], 201);
    }

    /**
     * Obtener estado de validación del usuario
     */
    public function obtenerEstadoValidacion(Request $request)
    {
        $validaciones = ValidacionCedulaManual::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        if ($validaciones->isEmpty()) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No tienes solicitudes de validación.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'data' => $validaciones
        ], 200);
    }

    /**
     * Listar todas las validaciones pendientes (Admin)
     */
    public function listarValidacionesPendientes()
    {
        $validaciones = ValidacionCedulaManual::with(['user', 'revisor'])
            ->where('estado', 'pendiente')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $validaciones
        ], 200);
    }

    /**
     * Aprobar o rechazar validación (Admin)
     */
    public function revisarValidacion(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => 'required|in:aprobado,rechazado',
            'notas_admin' => 'nullable|string|max:1000',
        ]);

        $validacion = ValidacionCedulaManual::findOrFail($id);

        $validacion->update([
            'estado' => $validated['estado'],
            'notas_admin' => $validated['notas_admin'] ?? null,
            'fecha_revision' => now(),
            'revisado_por' => auth()->id(),
        ]);

        // Si fue aprobado, agregar/actualizar la cédula en el perfil del usuario
        if ($validated['estado'] === 'aprobado') {
            $user = $validacion->user;
            $educacion = $user->educacion ?? [];

            // Estructura de la nueva cédula
            $nuevaCedula = [
                'cedula' => $validacion->numero_cedula,
                'profesion' => $validacion->carrera,
                'institucion' => $validacion->institucion,
                'fecha_expedicion' => $validacion->fecha_expedicion->format('Y-m-d'),
                'status' => 'aprobado',
                'validacion_id' => $validacion->id
            ];

            // Buscar si ya existe una cédula con validacion_manual_pendiente y actualizarla
            $escuelas = $educacion['escuelas'] ?? [];
            $actualizado = false;

            foreach ($escuelas as $key => $escuela) {
                if (isset($escuela['validacion_id']) && $escuela['validacion_id'] == $validacion->id) {
                    $escuelas[$key] = $nuevaCedula;
                    $actualizado = true;
                    break;
                }
            }

            // Si no se encontró, agregar como nueva
            if (!$actualizado) {
                $escuelas[] = $nuevaCedula;
            }

            $educacion['escuelas'] = $escuelas;

            $user->educacion = $educacion;
            $user->save();
        }

        return Inertia::location(route('psicologoShow', $user->id));
    }

    /**
     * Eliminar validación rechazada para permitir reintentar
     */
    public function eliminarValidacionRechazada($id)
    {
        $validacion = ValidacionCedulaManual::findOrFail($id);

        // Verificar que la validación pertenezca al usuario actual
        if ($validacion->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para eliminar esta validación.'
            ], 403);
        }

        // Solo permitir eliminar validaciones rechazadas
        if ($validacion->estado !== 'rechazado') {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo se pueden eliminar validaciones rechazadas.'
            ], 400);
        }

        // Nota: Los archivos en Cloudinary se mantienen por historial
        // Si se desea eliminar, agregar lógica con public_id

        // Eliminar del perfil del usuario
        $user = $validacion->user;
        $educacion = $user->educacion ?? [];
        $escuelas = $educacion['escuelas'] ?? [];

        $escuelas = array_filter($escuelas, function ($escuela) use ($id) {
            return !isset($escuela['validacion_id']) || $escuela['validacion_id'] != $id;
        });

        $educacion['escuelas'] = array_values($escuelas);
        $user->educacion = $educacion;
        $user->save();

        // Eliminar el registro de validación
        $validacion->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Validación eliminada correctamente. Puedes enviar una nueva solicitud.'
        ], 200);
    }

    /**
     * Actualizar una validación rechazada
     */
    public function actualizarValidacionRechazada(Request $request, $id)
    {
        $validacion = ValidacionCedulaManual::findOrFail($id);

        // Verificar que la validación pertenezca al usuario actual
        if ($validacion->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para actualizar esta validación.'
            ], 403);
        }

        // Solo permitir actualizar validaciones rechazadas
        if ($validacion->estado !== 'rechazado') {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo se pueden actualizar validaciones rechazadas.'
            ], 400);
        }

        $validated = $request->validate([
            'numero_cedula' => 'required|string|max:20',
            'nombre_completo' => 'required|string|max:255',
            'institucion' => 'required|string|max:255',
            'carrera' => 'required|string|max:255',
            'fecha_expedicion' => 'required|date',
            'archivo_cedula' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'archivo_titulo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Actualizar datos básicos
        $validacion->numero_cedula = $validated['numero_cedula'];
        $validacion->nombre_completo = $validated['nombre_completo'];
        $validacion->institucion = $validated['institucion'];
        $validacion->carrera = $validated['carrera'];
        $validacion->fecha_expedicion = $validated['fecha_expedicion'];
        $validacion->estado = 'pendiente'; // Volver a estado pendiente
        $validacion->fecha_revision = null;
        $validacion->revisado_por = null;
        $validacion->notas_admin = null;

        // Actualizar archivos si se enviaron nuevos
        if ($request->hasFile('archivo_cedula')) {
            // Subir nuevo archivo a Cloudinary
            $url = $this->uploadToCloudinary($request->file('archivo_cedula'), 'cedulas');
            $validacion->archivo_cedula = $url;
        }

        if ($request->hasFile('archivo_titulo')) {
            // Subir nuevo archivo a Cloudinary
            $url = $this->uploadToCloudinary($request->file('archivo_titulo'), 'titulos');
            $validacion->archivo_titulo = $url;
        }

        $validacion->save();

        // Actualizar en el perfil del usuario
        $user = $validacion->user;
        $educacion = $user->educacion ?? [];
        $escuelas = $educacion['escuelas'] ?? [];

        foreach ($escuelas as $key => $escuela) {
            if (isset($escuela['validacion_id']) && $escuela['validacion_id'] == $id) {
                $escuelas[$key] = [
                    'cedula' => $validacion->numero_cedula,
                    'profesion' => $validacion->carrera,
                    'institucion' => $validacion->institucion,
                    'fecha_expedicion' => $validacion->fecha_expedicion,
                    'status' => 'validacion_manual_pendiente',
                    'validacion_id' => $validacion->id
                ];
                break;
            }
        }

        $educacion['escuelas'] = $escuelas;
        $user->educacion = $educacion;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Validación actualizada correctamente. Será revisada nuevamente.',
            'validacion_id' => $validacion->id,
            'data' => $validacion
        ], 200);
    }

    /**
     * Subir archivo a Cloudinary
     */
    private function uploadToCloudinary($file, $folder)
    {
        try {
            $uploader = new UploadApi();
            $result = $uploader->upload($file->getRealPath(), [
                'folder' => 'cedulas_profesionales/' . $folder,
                'resource_type' => 'auto', // Detecta automáticamente si es imagen o PDF
            ]);

            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Error al subir archivo a Cloudinary: ' . $e->getMessage());
            throw new \Exception('Error al subir archivo: ' . $e->getMessage());
        }
    }

    function validateAndConvertEncoding($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->validateAndConvertEncoding($value);
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = $this->validateAndConvertEncoding($value);
            }
        } elseif (is_string($data)) {
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
        }
        return $data;
    }
}
