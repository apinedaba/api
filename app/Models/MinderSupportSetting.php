<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinderSupportSetting extends Model
{
    protected $fillable = [
        'support_email',
        'duration_minutes',
        'minimum_notice_hours',
        'booking_window_days',
        'weekly_availability',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'minimum_notice_hours' => 'integer',
        'booking_window_days' => 'integer',
        'weekly_availability' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'support_email' => 'mindmeetmx@gmail.com',
            'duration_minutes' => 30,
            'minimum_notice_hours' => 24,
            'booking_window_days' => 21,
            'weekly_availability' => [
                '1' => [['start' => '10:00', 'end' => '17:00']],
                '2' => [['start' => '10:00', 'end' => '17:00']],
                '3' => [['start' => '10:00', 'end' => '17:00']],
                '4' => [['start' => '10:00', 'end' => '17:00']],
                '5' => [['start' => '10:00', 'end' => '17:00']],
            ],
        ]);
    }
}
