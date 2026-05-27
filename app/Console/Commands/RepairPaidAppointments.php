<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentCart;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RepairPaidAppointments extends Command
{
    protected $signature = 'appointments:repair-paid';

    protected $description = 'Repair paid appointment carts that are missing an agenda session or video room.';

    public function handle(AppointmentService $appointmentService): int
    {
        $created = 0;
        $updated = 0;

        AppointmentCart::with(['patient', 'user'])
            ->where('estado', 'pagado')
            ->orderBy('id')
            ->chunkById(100, function ($carts) use ($appointmentService, &$created, &$updated) {
                foreach ($carts as $cart) {
                    if (!$cart->user_id || !$cart->patient_id || !$cart->fecha || !$cart->hora) {
                        continue;
                    }

                    $relation = $appointmentService->ensureRelationshipAndRoom($cart->user_id, $cart->patient_id);
                    $appointment = Appointment::where('cart_id', $cart->id)->first();

                    if (!$appointment) {
                        $appointment = $this->createAppointmentFromCart($cart, $relation->video_call_room);
                        $cart->forceFill(['appointment_id' => $appointment->id])->save();
                        $created++;
                        continue;
                    }

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
