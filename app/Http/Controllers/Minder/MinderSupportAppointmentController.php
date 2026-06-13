<?php

namespace App\Http\Controllers\Minder;

use App\Http\Controllers\Controller;
use App\Models\Administrator;
use App\Models\MinderSupportAppointment;
use App\Models\MinderSupportSetting;
use App\Notifications\MinderSupportAppointmentNotification;
use App\Services\MinderSupportScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MinderSupportAppointmentController extends Controller
{
    public function index(Request $request, MinderSupportScheduleService $schedule): JsonResponse
    {
        return response()->json([
            'appointments' => MinderSupportAppointment::where('user_id', $request->user()->id)
                ->where('scheduled_at', '>=', now()->subDay())
                ->latest('scheduled_at')
                ->get(),
            'slots' => $schedule->slots(),
            'settings' => MinderSupportSetting::current()->only(['duration_minutes', 'minimum_notice_hours']),
        ]);
    }

    public function store(Request $request, MinderSupportScheduleService $schedule): JsonResponse
    {
        $validated = $request->validate([
            'topic' => 'required|in:configuration,clinic,payments,marketing,training,other',
            'description' => 'required|string|min:20|max:2000',
            'scheduled_at' => 'required|date',
        ]);
        $settings = MinderSupportSetting::current();
        $date = Carbon::parse($validated['scheduled_at'])->timezone(config('app.timezone'));

        $appointment = DB::transaction(function () use ($request, $validated, $schedule, $settings, $date) {
            $lockedSettings = MinderSupportSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
            abort_unless($schedule->isAvailable($date, $lockedSettings), 422, 'Este horario ya no está disponible.');

            return MinderSupportAppointment::create([
                'user_id' => $request->user()->id,
                'topic' => $validated['topic'],
                'description' => $validated['description'],
                'scheduled_at' => $date,
                'duration_minutes' => $lockedSettings->duration_minutes,
                'status' => 'pending',
            ]);
        });

        $request->user()->notify(new MinderSupportAppointmentNotification($appointment, 'requested'));
        $administrators = Administrator::query()->get();
        Notification::send($administrators, new MinderSupportAppointmentNotification($appointment, 'requested'));

        if (! $administrators->pluck('email')->map(fn ($email) => strtolower($email))->contains(strtolower($settings->support_email))) {
            Notification::route('mail', $settings->support_email)
                ->notify(new MinderSupportAppointmentNotification($appointment, 'requested'));
        }

        return response()->json(['data' => $appointment, 'message' => 'Solicitud de horario enviada.'], 201);
    }

    public function cancel(Request $request, MinderSupportAppointment $appointment): JsonResponse
    {
        abort_if($appointment->user_id !== $request->user()->id, 403);
        abort_unless(in_array($appointment->status, ['pending', 'confirmed'], true), 422, 'Esta sesión ya no se puede cancelar.');

        $appointment->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $request->user()->notify(new MinderSupportAppointmentNotification($appointment, 'cancelled'));
        $settings = MinderSupportSetting::current();
        $administrators = Administrator::query()->get();
        Notification::send($administrators, new MinderSupportAppointmentNotification($appointment, 'cancelled'));

        if (! $administrators->pluck('email')->map(fn ($email) => strtolower($email))->contains(strtolower($settings->support_email))) {
            Notification::route('mail', $settings->support_email)
                ->notify(new MinderSupportAppointmentNotification($appointment, 'cancelled'));
        }

        return response()->json(['message' => 'Sesión cancelada.']);
    }
}
