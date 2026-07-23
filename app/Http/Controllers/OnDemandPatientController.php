<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentCart;
use App\Models\OnDemandOffer;
use App\Models\OnDemandRequest;
use App\Notifications\OnDemandMarketplaceNotification;
use App\Services\AppointmentService;
use App\Services\OnDemandMatchingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnDemandPatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(OnDemandRequest::query()
            ->with(['acceptedProfessional:id,name,image,contacto', 'appointment', 'offers.professional:id,name,image,contacto'])
            ->where('patient_id', $request->user()->id)
            ->latest()
            ->paginate(15));
    }

    public function store(Request $request, OnDemandMatchingService $matching): JsonResponse
    {
        $validated = $request->validate([
            'urgency' => ['nullable', 'in:now,today,next_24_hours'],
            'modality' => ['required', 'in:online,in_person'],
            'specialties' => ['nullable', 'array', 'max:5'],
            'specialties.*' => ['string', 'max:100'],
            'maximum_budget' => ['nullable', 'numeric', 'min:0'],
            'preferred_from' => ['nullable', 'date', 'after:now'],
            'preferred_until' => ['nullable', 'date', 'after:preferred_from'],
            'reason' => ['nullable', 'string', 'max:1500'],
            'location' => ['nullable', 'array'],
            'safety_screening' => ['required', 'array'],
            'safety_screening.immediate_danger' => ['required', 'boolean'],
            'safety_screening.self_harm_risk' => ['required', 'boolean'],
        ]);

        if (data_get($validated, 'safety_screening.immediate_danger') || data_get($validated, 'safety_screening.self_harm_risk')) {
            return response()->json([
                'message' => 'Esta modalidad no es adecuada para una emergencia. Busca ayuda de emergencia presencial inmediata.',
                'type' => 'crisis_redirect',
                'can_create_request' => false,
            ], 422);
        }

        $alreadyActive = OnDemandRequest::where('patient_id', $request->user()->id)
            ->whereIn('status', OnDemandRequest::ACTIVE_STATUSES)
            ->where('expires_at', '>', now())
            ->exists();
        if ($alreadyActive) {
            return response()->json(['message' => 'Ya tienes una solicitud on-demand activa.'], 422);
        }

        $from = isset($validated['preferred_from'])
            ? Carbon::parse($validated['preferred_from'])
            : now(config('app.timezone'))->addMinutes(15);
        $until = isset($validated['preferred_until'])
            ? Carbon::parse($validated['preferred_until'])
            : $from->copy()->addHours(4);

        $onDemand = DB::transaction(fn () => OnDemandRequest::create([
            ...$validated,
            'patient_id' => $request->user()->id,
            'status' => 'matching',
            'preferred_from' => $from,
            'preferred_until' => $until,
            'expires_at' => now(config('app.timezone'))->addMinutes(30),
        ]));

        $matching->match($onDemand);

        return response()->json($onDemand->fresh()
            ->load(['offers.professional:id,name,image,contacto']), 201);
    }

    public function show(Request $request, OnDemandRequest $onDemandRequest): JsonResponse
    {
        abort_unless($onDemandRequest->patient_id === $request->user()->id, 403);
        return response()->json($onDemandRequest->load([
            'acceptedProfessional:id,name,image,contacto,educacion',
            'appointment',
            'offers.professional:id,name,image,contacto,educacion',
        ]));
    }

    public function cancel(Request $request, OnDemandRequest $onDemandRequest): JsonResponse
    {
        abort_unless($onDemandRequest->patient_id === $request->user()->id, 403);
        abort_unless(in_array($onDemandRequest->status, OnDemandRequest::ACTIVE_STATUSES, true), 422, 'La solicitud ya no puede cancelarse.');

        DB::transaction(function () use ($onDemandRequest) {
            $onDemandRequest->offers()->where('status', 'pending')->update(['status' => 'cancelled']);
            if ($onDemandRequest->appointment) {
                $onDemandRequest->appointment->update([
                    'statusUser' => 'Cancel',
                    'statusPatient' => 'Cancel',
                    'state' => 'Cancelada',
                ]);
                $onDemandRequest->appointment->cart?->update(['estado' => 'cancelado']);
            }
            $onDemandRequest->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });

        return response()->json(['message' => 'Solicitud cancelada.', 'request' => $onDemandRequest->fresh()]);
    }

    public function select(Request $request, OnDemandRequest $onDemandRequest, AppointmentService $appointments): JsonResponse
    {
        abort_unless($onDemandRequest->patient_id === $request->user()->id, 403);
        $validated = $request->validate(['offer_id' => ['required', 'integer']]);

        $appointment = DB::transaction(function () use ($onDemandRequest, $validated, $appointments) {
            $lockedRequest = OnDemandRequest::whereKey($onDemandRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->status === 'candidates_ready', 409, 'La solicitud ya no permite elegir profesional.');
            abort_if($lockedRequest->expires_at->isPast(), 410, 'La solicitud vencio.');

            $offer = OnDemandOffer::whereKey($validated['offer_id'])
                ->where('on_demand_request_id', $lockedRequest->id)
                ->where('status', 'accepted')
                ->lockForUpdate()
                ->firstOrFail();
            $start = Carbon::parse($offer->proposed_start ?? $lockedRequest->preferred_from);
            $end = $start->copy()->addMinutes(50);
            abort_if(Appointment::where('user', $offer->professional_id)
                ->whereNotIn('statusPatient', ['Cancel'])
                ->where('start', '<', $end)->where('end', '>', $start)->exists(), 422, 'El horario acaba de ocuparse.');

            $relation = $appointments->ensureRelationshipAndRoom($offer->professional_id, $lockedRequest->patient_id);
            $price = $offer->price ?? 0;
            $appointment = Appointment::create([
                'user' => $offer->professional_id,
                'patient' => $lockedRequest->patient_id,
                'clinic_id' => $relation?->clinic_id,
                'title' => 'Sesion on-demand',
                'start' => $start,
                'end' => $end,
                'statusUser' => 'Pending Payment',
                'statusPatient' => 'Pending Payment',
                'state' => 'Pendiente de pago',
                'payment_status' => 'pending',
                'video_call_room' => $relation?->video_call_room,
                'extendedProps' => [
                    'source' => 'on_demand', 'tipoSesion' => 'individual',
                    'formato' => $lockedRequest->modality,
                    'payment_deadline' => now()->addMinutes(10)->toIso8601String(),
                ],
            ]);
            $cart = AppointmentCart::create([
                'patient_id' => $lockedRequest->patient_id, 'user_id' => $offer->professional_id,
                'appointment_id' => $appointment->id, 'fecha' => $start->toDateString(),
                'hora' => $start->format('H:i'), 'tipoSesion' => 'individual', 'duracion' => 50,
                'precio' => $price, 'originalPrice' => $price, 'estado' => 'pendiente',
                'formato' => $lockedRequest->modality, 'source' => 'on_demand',
            ]);
            $appointment->update(['cart_id' => $cart->id]);
            $lockedRequest->offers()->where('id', '!=', $offer->id)->whereIn('status', ['pending', 'accepted'])->update(['status' => 'expired']);
            $lockedRequest->update([
                'status' => 'awaiting_payment',
                'accepted_professional_id' => $offer->professional_id,
                'appointment_id' => $appointment->id,
                'accepted_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ]);
            return $appointment->fresh(['user', 'patient', 'cart']);
        });

        $appointment->user?->notify(new OnDemandMarketplaceNotification([
            'title' => 'El paciente te eligio',
            'body' => 'La reservacion esta esperando el pago del paciente.',
            'action_url' => rtrim(config('app.front_url_psicologo') ?: config('app.front_url_user'), '/').'/on-demand',
            'action_label' => 'Ver reservacion',
            'kind' => 'on-demand-professional-selected',
            'appointment_id' => $appointment->id,
            'on_demand_request_id' => $onDemandRequest->id,
        ]));

        return response()->json([
            'message' => 'Profesional seleccionado. Completa el pago para confirmar.',
            'appointment' => $appointment,
            'cart_id' => $appointment->cart_id,
            'payment_required' => true,
        ]);
    }
}
