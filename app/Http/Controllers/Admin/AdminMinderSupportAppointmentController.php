<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinderSupportAppointment;
use App\Models\MinderSupportSetting;
use App\Notifications\MinderSupportAppointmentNotification;
use App\Services\MinderSupportScheduleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminMinderSupportAppointmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Minder/SupportAppointments', [
            'appointments' => MinderSupportAppointment::with('user:id,name,email,image')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest('scheduled_at')
                ->paginate(30),
            'settings' => MinderSupportSetting::current(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'support_email' => 'required|email|max:255',
            'duration_minutes' => 'required|integer|in:30,45,60',
            'minimum_notice_hours' => 'required|integer|min:1|max:168',
            'booking_window_days' => 'required|integer|min:7|max:90',
            'weekly_availability' => 'required|array',
            'weekly_availability.*' => 'array',
            'weekly_availability.*.*.start' => 'required|date_format:H:i',
            'weekly_availability.*.*.end' => 'required|date_format:H:i|after:weekly_availability.*.*.start',
        ]);
        MinderSupportSetting::current()->update($data);

        return back()->with('success', 'Disponibilidad actualizada.');
    }

    public function update(Request $request, MinderSupportAppointment $appointment, MinderSupportScheduleService $schedule): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
            'scheduled_at' => 'required|date',
            'meeting_url' => 'nullable|url|max:500',
            'admin_notes' => 'nullable|string|max:2000',
        ]);
        $originalStatus = $appointment->status;
        $originalDate = $appointment->scheduled_at->copy();
        $date = Carbon::parse($data['scheduled_at'])->timezone(config('app.timezone'));

        DB::transaction(function () use ($appointment, $data, $date, $schedule) {
            $settings = MinderSupportSetting::query()->lockForUpdate()->firstOrFail();
            if (in_array($data['status'], ['pending', 'confirmed'], true)) {
                if (! $schedule->isAvailable($date, $settings, $appointment->id)) {
                    throw ValidationException::withMessages([
                        'scheduled_at' => 'El horario seleccionado ya no está disponible. Elige otra fecha u hora.',
                    ]);
                }
            }
            $appointment->update([
                ...$data,
                'scheduled_at' => $date,
                'cancelled_at' => $data['status'] === 'cancelled' ? now() : null,
            ]);
        });

        $event = match (true) {
            $data['status'] === 'cancelled' => 'cancelled',
            $data['status'] === 'completed' => 'completed',
            $data['status'] === 'confirmed' && $originalStatus !== 'confirmed' => 'confirmed',
            ! $originalDate->equalTo($date) => 'rescheduled',
            default => 'updated',
        };
        $appointment->user?->notify(new MinderSupportAppointmentNotification(
            $appointment,
            $event
        ));

        return back()->with('success', 'Sesión actualizada.');
    }
}
