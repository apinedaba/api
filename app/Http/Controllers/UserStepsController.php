<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\User;

class UserStepsController extends Controller
{
    public function getStepsForm($id)
    {
        $user = User::findOrFail($id);

        $stepsFile = storage_path('app/steps-profile.json'); // o usa directamente resources si lo cargas ahí
        $steps = json_decode(file_get_contents($stepsFile), true);

        $savedData = [];

        foreach ($steps as $step) {
            foreach ($step['fields'] as $field) {
                $segments = explode('.', $field['name']);
                $topKey = $segments[0];

                // Si ya existe, no sobrescribir
                if (!array_key_exists($topKey, $savedData)) {
                    $savedData[$topKey] = $user->{$topKey} ?? [];
                }
            }
        }

        return response()->json([
            'savedData' => $savedData
        ]);
    }

    public function saveStep(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // 1. Guardamos la data (igual que antes)
        $jsonFields = collect($user->getCasts())
            ->filter(fn($cast) => in_array($cast, ['array', 'json']))
            ->keys()
            ->toArray();

        foreach ($jsonFields as $key) {
            if ($request->has($key)) {
                $existing = $user->{$key} ?? [];
                $incoming = $request->input($key);

                if (is_array($existing) && is_array($incoming)) {
                    $user->{$key} = array_merge($existing, $incoming);
                } else {
                    $user->{$key} = $incoming;
                }
            }
        }

        // 2. Validamos si es el último paso
        $currentStepId = $request->input('step_id');
        $stepsPath = storage_path('app/steps-profile.json');
        $steps = json_decode(file_get_contents($stepsPath), true);
        $lastStepId = collect($steps)->pluck('id')->max();

        if ((int) $currentStepId === (int) $lastStepId) {
            $user->isProfileComplete = true;
        }

        $user->save();

        return response()->json([
            'status' => 'step_saved',
            'profileComplete' => $user->isProfileComplete,
            'saveData' => $this->getStepsForm($id)->original['savedData']
        ]);
    }

    public function completeProfile($id)
    {
        $user = $user = auth()->user();
        $user->isProfileComplete = true;
        $user->save();

        return response()->json(['status' => 'profile_complete']);
    }
}
