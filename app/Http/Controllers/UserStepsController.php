<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\User;

class UserStepsController extends Controller
{
    public function getStepsForm($id)
    {
        $user = $user = auth()->user();

        return response()->json([
            'savedData' => [
                'contacto' => $user->contacto ?? [],
                'direccion' => $user->direccion ?? [],
                'especialidades' => $user->especialidades ?? []
            ]
        ]);
    }

    public function saveStep(Request $request, $id)
    {
        $user = $user = auth()->user();
        $data = $request->all();

        foreach (['contacto', 'direccion', 'especialidades'] as $key) {
            if ($request->has($key)) {
                $existing = $user->{$key} ?? [];
                $incoming = $request->input($key);

                if (is_array($existing) && is_array($incoming)) {
                    $merged = array_merge($existing, $incoming);
                    $user->{$key} = $merged;
                } else {
                    $user->{$key} = $incoming;
                }
            }
        }

        $user->save();

        return response()->json(['status' => 'step_saved']);
    }

    public function completeProfile($id)
    {
        $user = $user = auth()->user();
        $user->isProfileComplete = true;
        $user->save();

        return response()->json(['status' => 'profile_complete']);
    }
}
