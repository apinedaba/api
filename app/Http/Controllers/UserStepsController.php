<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserStepsController extends Controller
{
    public function getStepsForm($id)
    {
        $user = $this->authenticatedUser($id);

        $steps = $this->profileSteps();

        $savedData = [];

        foreach ($steps as $step) {
            foreach ($step['fields'] as $field) {
                $segments = explode('.', $field['name']);
                $topKey = $segments[0];

                // Si ya existe, no sobrescribir
                if (!array_key_exists($topKey, $savedData)) {
                    $savedData[$topKey] = $user->{$topKey} ?? $topKey == "image" ? "" : [];
                }
            }
        }

        return response()->json([
            'savedData' => $user
        ]);
    }

    public function saveStep(Request $request, $id)
    {
        $user = $this->authenticatedUser($id);

        if ((int) $request->input('step_id') === 1) {
            $request->validate([
                'contacto.telefono' => ['required', 'regex:/^\d{10}$/'],
                'contacto.whatsapp' => ['required', 'regex:/^\d{10}$/'],
                'contacto.publicName' => ['required', 'string', 'max:255'],
            ]);
        }

        if ((int) $request->input('step_id') === 7) {
            $request->validate([
                'configurations.onboarding_quiz.expectation' => ['required', 'in:more_patients,organize_practice,explore,connect_psychologists,other'],
                'configurations.onboarding_quiz.expectation_other' => ['nullable', 'required_if:configurations.onboarding_quiz.expectation,other', 'string', 'max:255'],
                'configurations.onboarding_quiz.source' => ['required', 'in:meta,colleague,tiktok,google,other'],
                'configurations.onboarding_quiz.source_other' => ['nullable', 'required_if:configurations.onboarding_quiz.source,other', 'string', 'max:255'],
            ]);
        }

        $steps = $this->profileSteps();
        $currentStepId = (int) $request->input('step_id');
        $currentStep = collect($steps)->firstWhere('id', $currentStepId);

        if (! $currentStep) {
            throw ValidationException::withMessages(['step_id' => 'El paso indicado no es valido.']);
        }

        $allowedKeys = collect($currentStep['fields'] ?? [])
            ->pluck('name')
            ->map(fn ($name) => explode('.', $name)[0])
            ->unique();

        foreach ($allowedKeys as $key) {
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
        $lastStepId = collect($steps)->pluck('id')->max();

        if ((int) $currentStepId === (int) $lastStepId) {
            $user->isProfileComplete = true;
        }

        $user->save();
        $user->refresh()->syncOperationalStatus();

        return response()->json([
            'status' => 'step_saved',
            'profileComplete' => $user->isProfileComplete,
            'saveData' => $this->getStepsForm($id)->original['savedData']
        ]);
    }

    public function completeProfile($id)
    {
        $user = $this->authenticatedUser($id);

        if (! $user->hasValidPhone()) {
            throw ValidationException::withMessages([
                'contacto.telefono' => 'Registra un telefono valido de 10 digitos antes de completar el perfil.',
            ]);
        }

        $user->isProfileComplete = true;
        $user->save();
        $user->refresh()->syncOperationalStatus();

        return response()->json(['status' => 'profile_complete']);
    }

    private function authenticatedUser($id): User
    {
        $user = auth()->user();

        abort_unless($user && (int) $user->id === (int) $id, 403);

        return $user;
    }

    private function profileSteps(): array
    {
        $steps = json_decode(file_get_contents(storage_path('app/steps-profile.json')), true) ?: [];
        $trackedSteps = json_decode(file_get_contents(app_path('steps-profile.json')), true) ?: [];
        $quizStep = collect($trackedSteps)->firstWhere('id', 7);

        if ($quizStep && ! collect($steps)->contains('id', 7)) {
            $steps[] = $quizStep;
        }

        return $steps;
    }
}
