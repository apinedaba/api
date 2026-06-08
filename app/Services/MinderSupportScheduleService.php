<?php

namespace App\Services;

use App\Models\MinderSupportAppointment;
use App\Models\MinderSupportSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MinderSupportScheduleService
{
    public function slots(?MinderSupportSetting $settings = null, ?int $exceptAppointmentId = null): Collection
    {
        $settings ??= MinderSupportSetting::current();
        $timezone = config('app.timezone');
        $from = now($timezone)->addHours($settings->minimum_notice_hours);
        $until = now($timezone)->addDays($settings->booking_window_days)->endOfDay();
        $occupied = MinderSupportAppointment::whereIn('status', ['pending', 'confirmed'])
            ->when($exceptAppointmentId, fn ($query) => $query->where('id', '!=', $exceptAppointmentId))
            ->whereBetween('scheduled_at', [$from, $until])
            ->pluck('scheduled_at')
            ->map(fn ($date) => Carbon::parse($date, $timezone)->utc()->format('Y-m-d H:i'))
            ->flip();
        $slots = collect();

        for ($day = now($timezone)->startOfDay(); $day->lte($until); $day->addDay()) {
            foreach ($settings->weekly_availability[(string) $day->dayOfWeekIso] ?? [] as $range) {
                $cursor = $day->copy()->setTimeFromTimeString($range['start']);
                $end = $day->copy()->setTimeFromTimeString($range['end']);
                while ($cursor->copy()->addMinutes($settings->duration_minutes)->lte($end)) {
                    $utcKey = $cursor->copy()->utc()->format('Y-m-d H:i');
                    if ($cursor->gte($from) && ! $occupied->has($utcKey)) {
                        $slots->push([
                            'value' => $cursor->copy()->utc()->toIso8601String(),
                            'date' => $cursor->translatedFormat('D d M'),
                            'time' => $cursor->format('H:i'),
                        ]);
                    }
                    $cursor->addMinutes($settings->duration_minutes);
                }
            }
        }

        return $slots;
    }

    public function isAvailable(Carbon $date, ?MinderSupportSetting $settings = null, ?int $exceptAppointmentId = null): bool
    {
        return $this->slots($settings, $exceptAppointmentId)->contains('value', $date->copy()->utc()->toIso8601String());
    }
}
