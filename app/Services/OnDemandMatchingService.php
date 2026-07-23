<?php

namespace App\Services;

use App\Models\OnDemandOffer;
use App\Models\OnDemandProfessionalSetting;
use App\Models\OnDemandRequest;
use App\Notifications\OnDemandMarketplaceNotification;
use Illuminate\Support\Collection;

class OnDemandMatchingService
{
    public function match(OnDemandRequest $request, int $limit = 5): Collection
    {
        $now = now(config('app.timezone'));
        $alreadyOffered = $request->offers()->pluck('professional_id');
        $candidates = OnDemandProfessionalSetting::query()
            ->with('professional')
            ->whereNotIn('user_id', $alreadyOffered)
            ->where('is_available', true)
            ->where(fn ($query) => $query->whereNull('available_until')->orWhere('available_until', '>', $now))
            ->where(fn ($query) => $query->whereNull('next_available_at')->orWhere('next_available_at', '<=', $request->preferred_until))
            ->get()
            ->filter(fn ($setting) => $setting->professional?->activo !== false)
            ->map(fn ($setting) => $this->score($request, $setting))
            ->filter()
            ->sortByDesc('score')
            ->take($limit);

        $offers = $candidates->map(function (array $candidate) use ($request, $now) {
            $setting = $candidate['setting'];
            $expiresAt = $now->copy()->addMinutes($setting->response_window_minutes ?: 10);

            $offer = OnDemandOffer::updateOrCreate(
                ['on_demand_request_id' => $request->id, 'professional_id' => $setting->user_id],
                [
                    'status' => 'pending',
                    'match_score' => $candidate['score'],
                    'match_reasons' => $candidate['reasons'],
                    'proposed_start' => $request->preferred_from,
                    'price' => $setting->minimum_price,
                    'expires_at' => $expiresAt->min($request->expires_at),
                ]
            );
            $setting->professional?->notify(new OnDemandMarketplaceNotification([
                'title' => 'Nueva solicitud on-demand',
                'body' => 'Tienes una solicitud compatible esperando respuesta.',
                'action_url' => rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user'), '/').'/on-demand',
                'action_label' => 'Ver solicitud',
                'kind' => 'on-demand-offer',
                'on_demand_request_id' => $request->id,
                'offer_id' => $offer->id,
            ]));

            return $offer;
        })->values();

        $request->update([
            'status' => $offers->isEmpty() ? 'matching' : 'offered',
            'matched_at' => $offers->isEmpty() ? null : $now,
        ]);

        return $offers;
    }

    private function score(OnDemandRequest $request, OnDemandProfessionalSetting $setting): ?array
    {
        $modalities = array_map('mb_strtolower', $setting->modalities ?: ['online']);
        if (! in_array(mb_strtolower($request->modality), $modalities, true)) return null;
        if ($request->maximum_budget && $setting->minimum_price && $setting->minimum_price > $request->maximum_budget) return null;

        $requested = collect($request->specialties ?: [])->map(fn ($value) => mb_strtolower(trim($value)))->filter();
        $professional = collect(data_get($setting->professional?->educacion, 'especialidades', []))
            ->map(fn ($value) => mb_strtolower(trim(is_array($value) ? ($value['label'] ?? $value['value'] ?? '') : $value)))
            ->filter();
        $matches = $requested->intersect($professional)->values();
        $score = 45 + ($matches->count() * 15);
        $reasons = ['Disponible ahora', 'Modalidad compatible'];
        if ($matches->isNotEmpty()) {
            $score += 20;
            $reasons[] = 'Especialidad compatible';
        }
        if (! $request->maximum_budget || ! $setting->minimum_price || $setting->minimum_price <= $request->maximum_budget) {
            $score += 15;
            $reasons[] = 'Presupuesto compatible';
        }

        return ['setting' => $setting, 'score' => min(100, $score), 'reasons' => $reasons];
    }
}
