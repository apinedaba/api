<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $payments = Payment::where('user_id', $user->id)->with(['appointment', 'patient']);
        return response()->json([
            'payments'=> $payments->get(),
            'total' => $payments->sum('amount')
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
        try {
            $payment = Payment::create($request->all());
            return response()->json([
                'rasson' => 'El pago se registro exitosamente.',
                'message' => "Pago registrado",
                'type' => "success",
                'payment' => $payment
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'rasson' => 'Ocurrio un errro al registrar el pago',
                'message' => "Pago no registrado",
                'type' => "error",
            ],  400);

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payments)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payments)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payments)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payments)
    {
        //
    }
}
