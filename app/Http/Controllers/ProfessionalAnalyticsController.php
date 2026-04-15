<?php

namespace App\Http\Controllers;

use App\Models\ConsultaContacto;
use App\Models\ProfessionalAnalyticsEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfessionalAnalyticsController extends Controller
{
    private const ALLOWED_EVENTS = [
        'profile_view',
        'phone_click',
        'whatsapp_click',
        'facebook_click',
        'instagram_click',
        'linkedin_click',
        'website_click',
        'lead_started',
        'lead_submitted',
    ];

    public function track(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'event_type' => 'required|string|in:' . implode(',', self::ALLOWED_EVENTS),
            'source' => 'nullable|string|max:80',
            'medium' => 'nullable|string|max:80',
            'campaign' => 'nullable|string|max:160',
            'landing_page' => 'nullable|string|max:160',
            'path' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:255',
            'session_id' => 'nullable|string|max:120',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $payload['ip_hash'] = $request->ip()
            ? hash('sha256', $request->ip() . '|' . config('app.key'))
            : null;

        ProfessionalAnalyticsEvent::create($payload);

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        $eventsQuery = ProfessionalAnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to]);

        $uniqueVisitorExpression = "COALESCE(session_id, ip_hash, CONCAT('event-', id))";

        $eventCounts = (clone $eventsQuery)
            ->selectRaw("event_type, COUNT(DISTINCT {$uniqueVisitorExpression}) as total")
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $rawEventCounts = (clone $eventsQuery)
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $sourceCounts = (clone $eventsQuery)
            ->selectRaw("COALESCE(source, 'sin_fuente') as source, COUNT(DISTINCT {$uniqueVisitorExpression}) as total")
            ->groupBy('source')
            ->pluck('total', 'source');

        $dailyEvents = (clone $eventsQuery)
            ->selectRaw("DATE(created_at) as date, event_type, COUNT(DISTINCT {$uniqueVisitorExpression}) as total")
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get();

        $leadsQuery = ConsultaContacto::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'totals' => [
                    'profile_views' => (int) ($eventCounts['profile_view'] ?? 0),
                    'whatsapp_clicks' => (int) ($eventCounts['whatsapp_click'] ?? 0),
                    'phone_clicks' => (int) ($eventCounts['phone_click'] ?? 0),
                    'facebook_clicks' => (int) ($eventCounts['facebook_click'] ?? 0),
                    'instagram_clicks' => (int) ($eventCounts['instagram_click'] ?? 0),
                    'linkedin_clicks' => (int) ($eventCounts['linkedin_click'] ?? 0),
                    'website_clicks' => (int) ($eventCounts['website_click'] ?? 0),
                    'lead_started' => (int) ($eventCounts['lead_started'] ?? 0),
                    'lead_submitted' => (int) ($eventCounts['lead_submitted'] ?? 0),
                    'leads' => (clone $leadsQuery)->count(),
                ],
                'raw_totals' => [
                    'profile_views' => (int) ($rawEventCounts['profile_view'] ?? 0),
                    'whatsapp_clicks' => (int) ($rawEventCounts['whatsapp_click'] ?? 0),
                    'phone_clicks' => (int) ($rawEventCounts['phone_click'] ?? 0),
                    'facebook_clicks' => (int) ($rawEventCounts['facebook_click'] ?? 0),
                    'instagram_clicks' => (int) ($rawEventCounts['instagram_click'] ?? 0),
                    'linkedin_clicks' => (int) ($rawEventCounts['linkedin_click'] ?? 0),
                    'website_clicks' => (int) ($rawEventCounts['website_click'] ?? 0),
                    'lead_started' => (int) ($rawEventCounts['lead_started'] ?? 0),
                    'lead_submitted' => (int) ($rawEventCounts['lead_submitted'] ?? 0),
                ],
                'counting_method' => 'unique_by_session_or_ip',
                'events_by_type' => $eventCounts,
                'events_by_source' => $sourceCounts,
                'leads_by_source' => (clone $leadsQuery)
                    ->selectRaw("COALESCE(lead_source, 'sin_fuente') as source, COUNT(*) as total")
                    ->groupBy('source')
                    ->pluck('total', 'source'),
                'daily_events' => $dailyEvents,
            ],
        ]);
    }
}
