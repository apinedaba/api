<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Response;
use Carbon\Carbon;
class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        
    }
    /**
     * Display a listing of the resource.
     */
    public function getAppoinmentsByPatient(Request $request)
    {        
        $user = Auth::user();   
        $requestAll = $request->all();
        $patientId = $requestAll['patient'];
        $enlaceId= $requestAll['id'];
        $filter=[
            'id' => $enlaceId,
            'patient' => $patientId,
            'user' => $user->id
        ];
        $enlace = PatientUser::where($filter)->first();

        if (!isset($enlace['id'])) {
            return response()->json([
                'rasson' => 'No se pudo obtener informacion de la  consulta',
                'message' => "No coincide la informacion",
                'type' => "error"
            ], 400);
        }

        $appoinments = Appointment::where('patient_user',$enlace['id'])->get();


        return response()->json($appoinments, 200);
    }



    public function getAvailableSlots(Request $request)
    {
        $userId  = $request->id;

        // Obtener la fecha de hoy
        $today = Carbon::today();
        // Obtener la fecha de 10 días a partir de hoy
        $endDate = $today->copy()->addDays(10);

        // Obtener citas del médico para los próximos 10 días

        $appointments = Appointment::whereHas('patient_user', function ($query) use ($userId) {
            $query->where('user', $userId);
        })->whereBetween('fecha', [$today, $endDate])->get();
        

        // Aquí defines los horarios en los que el médico trabaja, por ejemplo, de 9am a 5pm
        $workingHours = [
            '09:00:00', '10:00:00', '11:00:00', '12:00:00', '13:00:00',
            '14:00:00', '15:00:00', '16:00:00', '17:00:00'
        ];

        // Crear un array con los días y horarios disponibles
        $availableSlots = [];

        // Iterar sobre los próximos 10 días
        for ($date = $today; $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            // Obtener las citas ya reservadas para ese día
            $bookedSlots = $appointments->where('fecha', $dateString)->pluck('hora')->toArray();

            // Comparar horarios de trabajo con las citas reservadas para encontrar disponibles
            $availableTimes = array_diff($workingHours, $bookedSlots);
            // Añadir los horarios disponibles para este día
            if (!empty($availableTimes)) {
                $availableSlots[$dateString] = $availableTimes;
            }
        }

        return response()->json($availableSlots);
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
        $appointment = Appointment::create($request->all())->save();
        return response()->json($appointment, 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Appointment $appointment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Appointment $appointment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment)
    {
        //
    }
}
