<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Patient;
use App\Notifications\NuevoPsicologoRegistrado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{

    private $registerValidationRules = [
        'name' => 'required',
        'email' => 'required|email|unique:users,email',
        'password' => 'required'
    ];

    public function registerUser(Request $request) {
        $validateUser = Validator::make($request->all(), $this->registerValidationRules);
        
        if($validateUser->fails()){
            return response()->json([
                'message' => 'Ha ocurrido un error de validación',
                'errors' => $validateUser->errors()
            ], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
        
        if ($user) {            
            try {
                //code...
                $user->notify(new NuevoPsicologoRegistrado($user));
                event(new Registered($user));
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                //throw $th;
            }
        }
        return response()->json([
            'rasson' => "Perfecto, te registraste con exito",
            'message' => "¡Te haz registrado!",
            'type' => "success",            
        ], 200);
    }

    private $registerValidationRulesPatient = [
        'name' => 'required',
        'email' => 'required|email|unique:patients,email',
        'password' => 'required'
    ];
    public function registerPatient(Request $request) {
        $validateUser = Validator::make($request->all(), $this->registerValidationRulesPatient);
        
        if($validateUser->fails()){
            return response()->json([
                'message' => 'Ha ocurrido un error de validación',
                'errors' => $validateUser->errors()
            ], 400);
        }

        $user = Patient::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'El usuario se ha creado',
            'token' => $user->createToken("patient_token")->plainTextToken
        ], 200);
    }
}