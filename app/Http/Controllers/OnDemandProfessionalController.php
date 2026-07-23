<?php

namespace App\Http\Controllers;

use App\Models\OnDemandOffer;
use App\Models\OnDemandProfessionalSetting;
use App\Models\OnDemandRequest;
use App\Notifications\OnDemandMarketplaceNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnDemandProfessionalController extends Controller
{
    public function settings(Request $request): JsonResponse
    {
        return response()->json(OnDemandProfessionalSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['modalities' => ['online'], 'response_window_minutes' => 10]
        ));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
            'modalities' => ['required', 'array', 'min:1'],
            'modalities.*' => ['in:online,in_person'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'maximum_price' => ['nullable', 'numeric', 'gte:minimum_price'],
            'response_window_minutes' => ['nullable', 'integer', 'between:3,30'],
            'available_until' => ['nullable', 'date', 'after:now'],
            'next_available_at' => ['nullable', 'date'],
        ]);

        $settings = OnDemandProfessionalSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json($settings);
    }

    public function offers(Request $request): JsonResponse
    {
        return response()->json(OnDemandOffer::query()
            ->with(['request.patient:id,name,image'])
            ->where('professional_id', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted', 'rejected'])
            ->latest()
            ->paginate(20));
    }

    public function reject(Request $request, OnDemandOffer $offer): JsonResponse
    {
        abort_unless($offer->professional_id === $request->user()->id, 403);
        abort_unless($offer->status === 'pending', 422, 'La oferta ya fue atendida.');
        $offer->update(['status' => 'rejected', 'responded_at' => now()]);
        return response()->json(['message' => 'Oferta rechazada.', 'offer' => $offer]);
    }

    public function accept(Request $request, OnDemandOffer $offer): JsonResponse
    {
        abort_unless($offer->professional_id === $request->user()->id, 403);
        $validated = $request->validate([
            'proposed_start' => ['nullable', 'date', 'after:now'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $onDemand = DB::transaction(function () use ($offer, $validated) {
            $lockedOffer = OnDemandOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $onDemand = OnDemandRequest::whereKey($lockedOffer->on_demand_request_id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOffer->status === 'pending' && in_array($onDemand->status, OnDemandRequest::ACTIVE_STATUSES, true), 409, 'La solicitud ya no esta disponible.');
            abort_if($lockedOffer->expires_at->isPast() || $onDemand->expires_at->isPast(), 410, 'La oferta vencio.');

            $lockedOffer->update([
                'status' => 'accepted',
                'price' => $validated['price'] ?? $lockedOffer->price,
                'proposed_start' => $validated['proposed_start'] ?? $lockedOffer->proposed_start,
                'responded_at' => now(),
            ]);
            $selectionDeadline = now()->addMinutes(15);
            $onDemand->update([
                'status' => 'candidates_ready',
                'expires_at' => $onDemand->expires_at->greaterThan($selectionDeadline)
                    ? $onDemand->expires_at
                    : $selectionDeadline,
            ]);
            return $onDemand->fresh(['patient']);
        });

        $onDemand->patient?->notify(new OnDemandMarketplaceNotification([
            'title' => 'Un psicologo esta disponible',
            'body' => 'Ya tienes una nueva opcion compatible. Revisa su perfil y elige si deseas reservar.',
            'action_url' => rtrim(config('app.perfil_paciente_url'), '/').'/atencion-ahora',
            'action_label' => 'Ver opciones',
            'kind' => 'on-demand-candidate-ready',
            'on_demand_request_id' => $onDemand->id,
        ]));

        return response()->json([
            'message' => 'Tu disponibilidad fue enviada al paciente.',
            'offer' => $offer->fresh(),
        ]);
    }
}
