<?php

namespace App\Console\Commands;

use App\Http\Controllers\StripeController;
use App\Models\Appointment;
use App\Models\AppointmentCart;
use App\Services\AppointmentService;
use App\Services\CheckoutPricingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class RepairPaidAppointments extends Command
{
    protected $signature = 'appointments:repair-paid';

    protected $description = 'Repair paid appointment carts that are missing an agenda session or video room.';

    public function handle(
        AppointmentService $appointmentService,
        CheckoutPricingService $pricingService,
        StripeController $stripeController
    ): int
    {
        $created = 0;
        $updated = 0;

        AppointmentCart::with(['patient', 'user'])
            ->where(function ($query) {
                $query->where('estado', 'pagado')
                    ->orWhereNotNull('payment_intent_id');
            })
            ->orderBy('id')
            ->chunkById(100, function ($carts) use ($appointmentService, $pricingService, $stripeController, &$created, &$updated) {
                foreach ($carts as $cart) {
                    if (!$cart->user_id || !$cart->patient_id || !$cart->fecha || !$cart->hora) {
                        continue;
                    }

                    $appointment = Appointment::where('cart_id', $cart->id)->first();
                    if (! $appointment && filled($cart->payment_intent_id)) {
                        try {
                            Stripe::setApiKey(config('services.stripe.secret_key'));
                            $intent = PaymentIntent::retrieve([
                                'id' => $cart->payment_intent_id,
                                'expand' => ['charges.data.balance_transaction'],
                            ]);

                            if ($intent->status === 'succeeded') {
                                $chargeMode = $pricingService->normalizeChargeMode(
                                    data_get($intent, 'metadata.charge_mode') ?: $cart->charge_mode
                                );
                                $pricing = $pricingService->buildFromCart($cart, $chargeMode);
                                $stripeController->finalizeSuccessfulSessionPayment(
                                    $cart->id,
                                    $intent,
                                    $pricing,
                                    str_contains((string) data_get($intent, 'metadata.type'), 'oxxo') ? 'oxxo' : 'card'
                                );
                                $created++;
                                continue;
                            }
                        } catch (\Throwable $exception) {
                            Log::warning('Could not reconcile paid appointment cart', [
                                'cart_id' => $cart->id,
                                'payment_intent_id' => $cart->payment_intent_id,
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    if (!$appointment) {
                        if ($cart->estado !== 'pagado') {
                            continue;
                        }
                        $relation = $appointmentService->ensureRelationshipAndRoom($cart->user_id, $cart->patient_id);
                        $appointment = $this->createAppointmentFromCart($cart, $relation->video_call_room);
                        $cart->forceFill(['appointment_id' => $appointment->id])->save();
                        $created++;
                        continue;
                    }

                    $relation = $appointmentService->ensureRelationshipAndRoom($cart->user_id, $cart->patient_id);
                    $updates = [];
                    if (!$appointment->video_call_room && $relation->video_call_room) {
                        $updates['video_call_room'] = $relation->video_call_room;
                    }

                    $patientTitle = 'Sesión con ' . ($cart->patient?->name ?: 'Paciente MindMeet');
                    $professionalNames = array_filter([
                        $cart->user?->name,
                        data_get($cart->user?->contacto, 'publicName'),
                    ]);
                    $currentTitle = (string) $appointment->title;
                    $looksLikeProfessionalTitle = collect($professionalNames)
                        ->contains(fn ($name) => $currentTitle === 'Sesión con ' . $name);

                    if (!$currentTitle || $looksLikeProfessionalTitle) {
                        $updates['title'] = $patientTitle;
                    }

                    if (!$cart->appointment_id) {
                        $cart->forceFill(['appointment_id' => $appointment->id])->save();
                    }

                    if (!empty($updates)) {
                        $appointment->forceFill($updates)->save();
                        $updated++;
                    }
                }
            });

        $this->info("Paid appointments repaired. Created: {$created}. Updated: {$updated}.");

        return self::SUCCESS;
    }

    private function createAppointmentFromCart(AppointmentCart $cart, ?string $videoCallRoom): Appointment
    {
        $start = Carbon::parse("{$cart->fecha} {$cart->hora}");
        $duration = is_numeric($cart->duracion) ? (float) $cart->duracion : 1.0;
        $minutes = $duration <= 8 ? (int) round($duration * 60) : (int) round($duration);
        $patientName = $cart->patient?->name ?: 'Paciente MindMeet';

        return Appointment::create([
            'user' => $cart->user_id,
            'patient' => $cart->patient_id,
            'start' => $start,
            'end' => $start->copy()->addMinutes(max($minutes, 1)),
            'title' => 'Sesión con ' . $patientName,
            'statusUser' => 'Pending Approve',
            'statusPatient' => 'Pending Approve',
            'state' => $cart->charge_mode === 'avg' ? 'Pendiente de liquidar' : 'Creado',
            'cart_id' => $cart->id,
            'video_call_room' => $videoCallRoom,
            'extendedProps' => [
                'tipoSesion' => $cart->tipoSesion,
                'formato' => $cart->formato,
                'payment_status' => 'paid',
                'charge_mode' => $cart->charge_mode,
            ],
            'notification_meta' => [],
        ]);
    }
}
