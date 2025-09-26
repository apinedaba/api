<?php

namespace App\Http\Controllers;

use App\Models\ChatPublic;
use Illuminate\Http\Request;

class ChatPublicController extends Controller
{

    public function agregarComentarioPublico(Request $request) {
        $query = ChatPublic::where('paciente_id', $request?->paciente_id)
        ->where('psicologo_id', $request?->psicologo_id);
        $count = $query->count();
        $response = $count === 0 ? ChatPublic::create($request->all()): $query->update($request->all());
        
        return response()->json($response, 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function index($user, $patient)
    {
        $query = ChatPublic::where('paciente_id', $patient)
        ->where('psicologo_id', $user)->firstOrFail();
        return response()->json($query, 200);
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

    /**
     * Display the specified resource.
     */
    public function show(ChatPublic $chatPublic)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChatPublic $chatPublic)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ChatPublic $chatPublic)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChatPublic $chatPublic)
    {
        //
    }
}
