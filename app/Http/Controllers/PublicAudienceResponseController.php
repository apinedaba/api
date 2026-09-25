<?php

namespace App\Http\Controllers;

use App\Models\PublicAudienceResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAudienceResponseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'session_id' => ['required', 'string', 'max:120'],
            'audience' => ['required', 'in:psychologist,patient'],
            'route_choice' => ['required', 'in:professional_platform,guided_search,full_directory'],
            'destination' => ['required', 'in:/plataforma-para-psicologos,/encontrar-psicologo,/psicologos'],
            'landing_page' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:160'],
        ]);

        $validCombination = match ($payload['audience']) {
            'psychologist' => $payload['route_choice'] === 'professional_platform'
                && $payload['destination'] === '/plataforma-para-psicologos',
            'patient' => ($payload['route_choice'] === 'guided_search'
                    && $payload['destination'] === '/encontrar-psicologo')
                || ($payload['route_choice'] === 'full_directory'
                    && $payload['destination'] === '/psicologos'),
        };

        abort_unless($validCombination, 422, 'La combinación de respuesta y destino no es válida.');

        $payload['ip_hash'] = $request->ip()
            ? hash('sha256', $request->ip().'|'.config('app.key'))
            : null;

        $response = PublicAudienceResponse::query()->updateOrCreate(
            ['session_id' => $payload['session_id']],
            $payload
        );

        return response()->json([
            'status' => 'success',
            'id' => $response->id,
        ], $response->wasRecentlyCreated ? 201 : 200);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        $query = PublicAudienceResponse::query()->whereBetween('created_at', [$from, $to]);
        $total = (clone $query)->count();
        $audiences = (clone $query)->selectRaw('audience, COUNT(*) as total')
            ->groupBy('audience')->pluck('total', 'audience');
        $choices = (clone $query)->selectRaw('route_choice, COUNT(*) as total')
            ->groupBy('route_choice')->pluck('total', 'route_choice');
        $sources = (clone $query)->whereNotNull('utm_source')
            ->selectRaw('utm_source, COUNT(*) as total')->groupBy('utm_source')
            ->orderByDesc('total')->limit(10)->get();

        $patientTotal = (int) ($audiences['patient'] ?? 0);
        $guidedTotal = (int) ($choices['guided_search'] ?? 0);

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_responses' => $total,
            'audience' => [
                'psychologist' => (int) ($audiences['psychologist'] ?? 0),
                'patient' => $patientTotal,
            ],
            'route_choice' => [
                'professional_platform' => (int) ($choices['professional_platform'] ?? 0),
                'guided_search' => $guidedTotal,
                'full_directory' => (int) ($choices['full_directory'] ?? 0),
            ],
            'guided_search_rate' => $patientTotal > 0
                ? round(($guidedTotal / $patientTotal) * 100, 1)
                : 0,
            'top_sources' => $sources,
        ]);
    }
}
