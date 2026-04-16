<?php

namespace App\Http\Controllers;

use App\Models\ConsultaContacto;
use App\Models\ProfessionalAnalyticsEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
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

    private const EVENT_LABELS = [
        'profile_view' => 'Vistas de perfil',
        'phone_click' => 'Clicks telefono',
        'whatsapp_click' => 'Clicks WhatsApp',
        'facebook_click' => 'Clicks Facebook',
        'instagram_click' => 'Clicks Instagram',
        'linkedin_click' => 'Clicks LinkedIn',
        'website_click' => 'Clicks sitio web',
        'lead_started' => 'Leads iniciados',
        'lead_submitted' => 'Leads enviados',
    ];

    public function adminIndex(Request $request): Response
    {
        [$from, $to] = $this->resolveRange($request);
        $uniqueVisitorExpression = "COALESCE(session_id, ip_hash, CONCAT('event-', id))";

        $events = ProfessionalAnalyticsEvent::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("user_id, event_type, COUNT(DISTINCT {$uniqueVisitorExpression}) as unique_total, COUNT(*) as raw_total")
            ->groupBy('user_id', 'event_type')
            ->get()
            ->groupBy('user_id');

        $sources = ProfessionalAnalyticsEvent::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("user_id, COALESCE(source, 'sin_fuente') as source, COUNT(DISTINCT {$uniqueVisitorExpression}) as total")
            ->groupBy('user_id', 'source')
            ->orderByDesc('total')
            ->get()
            ->groupBy('user_id');

        $interactionSources = ProfessionalAnalyticsEvent::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("COALESCE(source, 'sin_fuente') as source, COUNT(DISTINCT {$uniqueVisitorExpression}) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $campaigns = ProfessionalAnalyticsEvent::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("COALESCE(campaign, 'sin_campana') as campaign, COUNT(DISTINCT {$uniqueVisitorExpression}) as total")
            ->groupBy('campaign')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $leadCounts = ConsultaContacto::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->selectRaw("user_id, COUNT(*) as total")
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $leadSources = ConsultaContacto::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->selectRaw("COALESCE(lead_source, 'sin_fuente') as source, COUNT(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $activeUserIds = collect($events->keys())
            ->merge($leadCounts->keys())
            ->filter()
            ->unique()
            ->values();

        $users = User::query()
            ->with('subscription')
            ->when($request->boolean('only_activity', true), function ($query) use ($activeUserIds) {
                $query->whereIn('id', $activeUserIds->isNotEmpty() ? $activeUserIds : [-1]);
            })
            ->orderBy('name')
            ->get();

        $professionals = $users->map(function (User $user) use ($events, $sources, $leadCounts) {
            $eventRows = $events->get($user->id, collect());
            $eventTotals = $eventRows->mapWithKeys(fn ($row) => [$row->event_type => (int) $row->unique_total]);
            $rawTotals = $eventRows->mapWithKeys(fn ($row) => [$row->event_type => (int) $row->raw_total]);
            $leads = (int) ($leadCounts[$user->id] ?? 0);
            $profileViews = (int) ($eventTotals['profile_view'] ?? 0);
            $leadSubmits = (int) ($eventTotals['lead_submitted'] ?? 0);
            $contactClicks = (int) (
                ($eventTotals['phone_click'] ?? 0)
                + ($eventTotals['whatsapp_click'] ?? 0)
                + ($eventTotals['facebook_click'] ?? 0)
                + ($eventTotals['instagram_click'] ?? 0)
                + ($eventTotals['linkedin_click'] ?? 0)
                + ($eventTotals['website_click'] ?? 0)
            );

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'activo' => (bool) $user->activo,
                'is_profile_complete' => (bool) $user->isProfileComplete,
                'identity_verification_status' => $user->identity_verification_status,
                'subscription_status' => $user->has_lifetime_access
                    ? 'lifetime'
                    : optional($user->subscription)->stripe_status,
                'totals' => [
                    'profile_views' => $profileViews,
                    'phone_clicks' => (int) ($eventTotals['phone_click'] ?? 0),
                    'whatsapp_clicks' => (int) ($eventTotals['whatsapp_click'] ?? 0),
                    'facebook_clicks' => (int) ($eventTotals['facebook_click'] ?? 0),
                    'instagram_clicks' => (int) ($eventTotals['instagram_click'] ?? 0),
                    'linkedin_clicks' => (int) ($eventTotals['linkedin_click'] ?? 0),
                    'website_clicks' => (int) ($eventTotals['website_click'] ?? 0),
                    'lead_started' => (int) ($eventTotals['lead_started'] ?? 0),
                    'lead_submitted' => $leadSubmits,
                    'leads' => $leads,
                    'contact_clicks' => $contactClicks,
                    'raw_profile_views' => (int) ($rawTotals['profile_view'] ?? 0),
                ],
                'rates' => [
                    'lead_conversion' => $profileViews > 0 ? round(($leads / $profileViews) * 100, 2) : 0,
                    'form_conversion' => $profileViews > 0 ? round(($leadSubmits / $profileViews) * 100, 2) : 0,
                    'contact_ctr' => $profileViews > 0 ? round(($contactClicks / $profileViews) * 100, 2) : 0,
                ],
                'sources' => $sources->get($user->id, collect())
                    ->take(5)
                    ->map(fn ($row) => [
                        'source' => $row->source,
                        'total' => (int) $row->total,
                    ])
                    ->values(),
            ];
        })->sortByDesc(fn ($row) => $row['totals']['profile_views'])->values();

        $summary = [
            'professionals_with_activity' => $professionals->count(),
            'profile_views' => $professionals->sum(fn ($row) => $row['totals']['profile_views']),
            'contact_clicks' => $professionals->sum(fn ($row) => $row['totals']['contact_clicks']),
            'leads' => $professionals->sum(fn ($row) => $row['totals']['leads']),
            'lead_conversion' => $professionals->sum(fn ($row) => $row['totals']['profile_views']) > 0
                ? round(($professionals->sum(fn ($row) => $row['totals']['leads']) / $professionals->sum(fn ($row) => $row['totals']['profile_views'])) * 100, 2)
                : 0,
        ];

        return Inertia::render('Analytics', [
            'analytics' => [
                'range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'summary' => $summary,
                'professionals' => $professionals,
                'eventLabels' => self::EVENT_LABELS,
                'topSources' => $leadSources,
                'topInteractionSources' => $interactionSources,
                'topCampaigns' => $campaigns,
                'countingMethod' => 'unique_by_session_or_ip',
            ],
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'only_activity' => $request->boolean('only_activity', true),
            ],
        ]);
    }

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
        [$from, $to] = $this->resolveRange($request);

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

    private function resolveRange(Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            return [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
