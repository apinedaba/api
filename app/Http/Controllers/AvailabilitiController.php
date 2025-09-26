<?php

namespace App\Http\Controllers;

use App\Models\Availabiliti;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AvailabilitiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $slots = Availabiliti::where('user_id', $user->id)->get();
        return response()->json($slots);
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
        $user = Auth::user();

        $request->validate([
            'slots' => 'required|array',
            'slots.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
        ]);

        // Eliminar disponibilidad anterior
        Availabiliti::where('user_id', $user->id)->delete();

        // Guardar nueva disponibilidad
        foreach ($request->slots as $slot) {
            Availabiliti::create([
                'user_id' => $user->id,
                'day' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
            ]);
        }

        return response()->json(['message' => 'Disponibilidad guardada con éxito.'], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Availabiliti $availabiliti)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Availabiliti $availabiliti)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Availabiliti $availabiliti)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Availabiliti $availabiliti)
    {
        //
    }
}
