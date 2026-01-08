<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PatientAttachmentController extends Controller
{
    public function index($id)
    {
        // TODO: Implementar
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    public function store(Request $request, $id)
    {
        // TODO: Implementar
        return response()->json([
            'success' => true,
            'message' => 'Funcionalidad pendiente de implementar'
        ]);
    }

    public function delete($id)
    {
        // TODO: Implementar
        return response()->json([
            'success' => true,
            'message' => 'Funcionalidad pendiente de implementar'
        ]);
    }
}
