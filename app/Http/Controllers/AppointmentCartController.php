<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppointmentCart;
use App\Models\Patient;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

    public function getAllCarts()
    {
        $carts = AppointmentCart::with('user')->with('patient')->get();
        return Inertia::render('Carts', [
            'carts' => $carts,
            'status' => session('status'),
        ]);
    }

    public function getCartById($id)
    {
        $cart = AppointmentCart::with('user')
            ->where('id', $id)
            ->latest()
            ->first();

        return Inertia::render('Cart', [
            'cart' => $cart,
            'status' => session('status'),
        ]);
    }

    public function getCartByPatient($patient)
    {
        $cart = AppointmentCart::with('user')
            ->where('patient_id', $patient)
            ->where('estado', 'pendiente')
            ->where('patient_id', $patient)
            ->latest()
            ->first();

        return Inertia::render('Cart', [
            'cart' => $cart,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipoSesion' => 'required|string',
            'duracion' => 'required|string',
            'precio' => 'required|integer',
        ]);
        // return response()->json($request->except(['categoria', 'user']) + [
        //         'estado' => 'pendiente',
        //     ]);
        $patient = auth()->user();  // auth:patient
        $cart = AppointmentCart::updateOrCreate(
            $request->except(['categoria', 'user']) + [
                'patient_id' => $patient->id,
                'estado' => $request->estado === 'pendientePago' ? 'pendientePago' : 'pendiente',
                'user_id' => $request->user_id,
            ]
        );
        return response()->json($cart);
    }

    /**
     * Display the specified resource.
     */
    public function show(AppointmentCart $appointmentCart)
    {
        $patient = auth()->user();
        $cart = AppointmentCart::with('user')
            ->where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->where('patient_id', $patient->id)
            ->latest()
            ->first();

        return response()->json($cart);
    }

    /**
     * Display the specified resource.
     */
    public function cartById(AppointmentCart $appointmentCart)
    {
        $patient = auth()->user();
        $cart = AppointmentCart::with('user')
            ->where('patient_id', $patient->id)
            ->where('estado', 'pendientePago')
            ->where('patient_id', $patient->id)
            ->latest()
            ->first();

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
