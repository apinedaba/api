<?php

namespace App\Http\Controllers;

use App\Models\AppointmentCart;
use App\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppointmentCartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipoSesion' => 'required|string',
            'duracion' => 'required|string',
            'precio' => 'required|integer',
        ]);

        $patient = auth()->user(); // auth:patient

        // (opcional) eliminar carrito anterior del paciente
        // AppointmentCart::where('patient_id', $patient->id)->delete();
        $cart = AppointmentCart::where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->latest();
        if($cart->exists()) {
            $cart = $cart->update([
                'patient_id' => $patient->id,
                'user_id' => $request->user_id,
                'fecha' => $request->fecha,
                'hora' => $request->hora,
                'tipoSesion' => $request->tipoSesion,
                'duracion' => $request->duracion,
                'precio' => $request->precio,
            ]);
        } else {
            $cart = AppointmentCart::create([
                'patient_id' => $patient->id,
                'user_id' => $request->user_id,
                'fecha' => $request->fecha,
                'hora' => $request->hora,
                'tipoSesion' => $request->tipoSesion,
                'duracion' => $request->duracion,
                'precio' => $request->precio,
                'estado' => 'pendiente',
            ]);
        }

        return response()->json($cart);
    }

    /**
     * Display the specified resource.
     */
    public function show(AppointmentCart $appointmentCart)
    {
       $patient = auth()->user();
        $cart = AppointmentCart::with('user')->where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->where('patient_id', $patient->id)
            ->latest()->first();

        return response()->json($cart);
    }

    public function pays(AppointmentCart $appointmentCart)
    {
        $user = auth()->user();
        $cart = AppointmentCart::with(['user', 'patient'])
            ->where('estado', 'pagado')
            ->where('user_id', $user->id)
            ->latest()
            ->get(); 


        return response()->json($cart);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AppointmentCart $appointmentCart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AppointmentCart $appointmentCart)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AppointmentCart $appointmentCart)
    {
        //
    }
}
