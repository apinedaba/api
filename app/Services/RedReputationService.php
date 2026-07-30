<?php

namespace App\Services;

use App\Models\RedPregunta;
use App\Models\RedRespuesta;
use App\Models\RedVoto;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RedReputationService
{
    public function summary(int $userId): array
    {
        return Cache::remember("red:reputation:{$userId}", now()->addMinutes(10), function () use ($userId) {
            $questions = RedPregunta::where('user_id', $userId)->where('is_active', true)->count();
            $answers = RedRespuesta::where('user_id', $userId)->where('is_deleted', false)->count();
            $accepted = RedPregunta::whereHas('mejorRespuesta', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('is_deleted', false))->count();
            $usefulVotes = RedVoto::whereHas('respuesta', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('is_deleted', false))->count();
            $score = $questions + ($answers * 2) + ($usefulVotes * 5) + ($accepted * 20);

            return [
                'score' => $score,
                'level' => $this->level($score),
                'questions_count' => $questions,
                'answers_count' => $answers,
                'accepted_answers_count' => $accepted,
                'useful_votes_count' => $usefulVotes,
                'badges' => $this->badges($questions, $answers, $accepted, $usefulVotes),
            ];
        });
    }

    public function profile(User $user): array
    {
        $education = is_array($user->educacion) ? $user->educacion : [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'image' => $user->image,
            'specialties' => collect($education['especialidades'] ?? [])
                ->map(fn ($specialty) => $this->specialtyLabel((string) $specialty))
                ->values()
                ->all(),
            'approach' => $this->approachLabel($education['enfoque'] ?? null),
            'experience' => $education['experiencia'] ?? null,
            'reputation' => $this->summary($user->id),
            'recent_questions' => RedPregunta::where('user_id', $user->id)
                ->where('is_active', true)
                ->latest()
                ->limit(3)
                ->get(['id', 'titulo', 'created_at']),
            'recent_answers' => RedRespuesta::where('user_id', $user->id)
                ->where('is_deleted', false)
                ->with('pregunta:id,titulo')
                ->latest()
                ->limit(3)
                ->get(['id', 'pregunta_id', 'contenido', 'created_at']),
        ];
    }

    public function forget(int $userId): void
    {
        Cache::forget("red:reputation:{$userId}");
    }

    private function specialtyLabel(string $specialty): string
    {
        $catalog = Cache::rememberForever('red:specialty-labels', function () {
            $path = resource_path('json/especialidades.json');
            $items = file_exists($path) ? json_decode(file_get_contents($path), true) : [];

            return collect($items)->pluck('label', 'value')->all();
        });

        return $catalog[$specialty] ?? $this->humanize($specialty);
    }

    private function approachLabel(mixed $approach): ?string
    {
        if (! is_string($approach) || trim($approach) === '') {
            return null;
        }

        $normalized = Str::lower(trim($approach));
        $labels = [
            'cognitive-conductual' => 'Enfoque Cognitivo-Conductual',
            'cognitive behavioral' => 'Enfoque Cognitivo-Conductual',
            'cognitive_behavioral' => 'Enfoque Cognitivo-Conductual',
            'cognitive_behavioral_therapy' => 'Enfoque Cognitivo-Conductual',
            'psychoanalytic' => 'Enfoque Psicoanalítico o Psicodinámico',
            'psychodynamic' => 'Enfoque Psicoanalítico o Psicodinámico',
            'humanistic' => 'Enfoque Humanista',
            'systemic' => 'Enfoque Sistémico',
            'neuropsychological' => 'Enfoque Neuropsicológico',
            'behavioral' => 'Enfoque Conductista',
            'integrative' => 'Enfoque Integrativo',
        ];

        return $labels[$normalized] ?? $approach;
    }

    private function humanize(string $value): string
    {
        return Str::of($value)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();
    }

    private function level(int $score): array
    {
        return match (true) {
            $score >= 500 => ['key' => 'referente', 'name' => 'Referente', 'next_at' => null],
            $score >= 200 => ['key' => 'especialista', 'name' => 'Especialista', 'next_at' => 500],
            $score >= 75 => ['key' => 'colaborador', 'name' => 'Colaborador', 'next_at' => 200],
            $score >= 20 => ['key' => 'participante', 'name' => 'Participante', 'next_at' => 75],
            default => ['key' => 'nuevo', 'name' => 'Nuevo en la red', 'next_at' => 20],
        };
    }

    private function badges(int $questions, int $answers, int $accepted, int $usefulVotes): array
    {
        return collect([
            $answers >= 1 ? ['key' => 'primera_aportacion', 'name' => 'Primera aportación', 'description' => 'Publicó su primera respuesta.'] : null,
            $usefulVotes >= 5 ? ['key' => 'respuesta_util', 'name' => 'Respuesta útil', 'description' => 'Recibió al menos 5 votos útiles.'] : null,
            $accepted >= 1 ? ['key' => 'caso_resuelto', 'name' => 'Caso resuelto', 'description' => 'Una respuesta fue aceptada como la mejor.'] : null,
            $accepted >= 5 ? ['key' => 'referente_clinico', 'name' => 'Referente clínico', 'description' => 'Resolvió al menos 5 preguntas.'] : null,
            $questions >= 10 ? ['key' => 'curiosidad_clinica', 'name' => 'Curiosidad clínica', 'description' => 'Compartió al menos 10 preguntas.'] : null,
        ])->filter()->values()->all();
    }
}
