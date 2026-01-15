<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OfficeController extends Controller
{
    /**
     * Obtener el consultorio del psicólogo autenticado
     */
    public function show()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $office = $user->activeOffice;

        if (!$office) {
            return response()->json([
                'message' => 'No hay consultorio registrado',
                'office' => null
            ], 200);
        }

        return response()->json([
            'office' => $office
        ], 200);
    }

    /**
     * Crear o actualizar el consultorio del psicólogo
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zip_code' => 'required|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Desactivar consultorios anteriores si se está activando uno nuevo
        if ($request->get('is_active', true)) {
            Office::where('user_id', $user->id)->update(['is_active' => false]);
        }

        $office = Office::updateOrCreate(
            ['user_id' => $user->id, 'is_active' => true],
            $request->only(['address', 'city', 'state', 'zip_code', 'latitude', 'longitude', 'is_active'])
        );

        return response()->json([
            'message' => 'Consultorio guardado exitosamente',
            'office' => $office
        ], 200);
    }

    /**
     * Buscar psicólogos por ubicación (estado o código postal)
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Office::with(['user' => function ($q) {
            $q->where('activo', true)
                ->select('id', 'name', 'email', 'image', 'personales', 'educacion');
        }])
            ->where('is_active', true);

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('state') && $request->state) {
            $query->where('state', 'like', '%' . $request->state . '%');
        }

        if ($request->has('zip_code') && $request->zip_code) {
            $query->where('zip_code', $request->zip_code);
        }

        $offices = $query->get();

        // Filtrar los que tienen usuario activo
        $results = $offices->filter(function ($office) {
            return $office->user !== null;
        })->values();

        return response()->json([
            'results' => $results,
            'count' => $results->count()
        ], 200);
    }

    /**
     * Obtener todos los consultorios del psicólogo autenticado
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $offices = $user->offices()->orderBy('is_active', 'desc')->get();

        return response()->json([
            'offices' => $offices
        ], 200);
    }

    /**
     * Eliminar un consultorio
     */
    public function destroy($id)
    {
        $office = Office::where('user_id', Auth::id())->findOrFail($id);
        $office->delete();

        return response()->json([
            'message' => 'Consultorio eliminado exitosamente'
        ], 200);
    }
}
