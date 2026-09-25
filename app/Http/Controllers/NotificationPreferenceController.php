<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $saved = NotificationPreference::query()->whereMorphedTo('notifiable', $request->user())->get()->keyBy('event_key');
        $events = collect(NotificationPreferenceService::CATALOG)->map(function ($definition, $key) use ($saved) {
            $preference = $saved->get($key);
            return ['event_key' => $key, ...$definition, 'enabled_channels' => $preference?->channels ?? $definition['channels']];
        })->values();
        return response()->json(['data' => $events]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event_key' => ['required', Rule::in(array_keys(NotificationPreferenceService::CATALOG))],
            'preferences.*.channels' => ['required', 'array'],
            'preferences.*.channels.*' => [Rule::in(['database', 'push', 'mail', 'whatsapp'])],
        ]);
        foreach ($validated['preferences'] as $item) {
            $allowed = NotificationPreferenceService::CATALOG[$item['event_key']]['channels'];
            NotificationPreference::updateOrCreate([
                'notifiable_type' => $request->user()::class, 'notifiable_id' => $request->user()->id, 'event_key' => $item['event_key'],
            ], ['channels' => array_values(array_intersect($item['channels'], $allowed)), 'timezone' => $request->user()->timezone ?: config('app.timezone')]);
        }
        return $this->index($request);
    }
}
