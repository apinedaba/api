<?php

namespace App\Http\Controllers;

use App\Models\ConsultaContacto;
use App\Models\Patient;
use App\Models\ProfessionalAnalyticsEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

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
        'checkout_started',
        'appointment_booked',
        'repeat_appointment_booked',
        'appointment_paid',
        'payment_completed',
        'session_completed',
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
        'checkout_started' => 'Checkouts iniciados',
        'appointment_booked' => 'Primeras citas agendadas',
        'repeat_appointment_booked' => 'Citas recurrentes agendadas',
        'appointment_paid' => 'Citas pagadas',
        'payment_completed' => 'Pagos confirmados',
        'session_completed' => 'Sesiones completadas',
    ];

    public function adminIndex(Request $request): Response
    {
        [$from, $to] = $this->resolveRange($request);
        $granularity = $this->resolveGranularity($request, $from, $to);
        $uniqueVisitorExpression = "COALESCE(session_id, ip_hash, CONCAT('event-', id))";
        $leadStatus = $request->query('lead_status');
        $activeLeadStatuses = ['new', 'viewed', 'contacted', 'created'];

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
            ->when($leadStatus === 'active', fn ($query) => $query->whereIn('status', $activeLeadStatuses))
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $leadSources = ConsultaContacto::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->when($leadStatus === 'active', fn ($query) => $query->whereIn('status', $activeLeadStatuses))
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
            $appointments = (int) ($eventTotals['appointment_booked'] ?? 0)
                + (int) ($eventTotals['repeat_appointment_booked'] ?? 0);
            $paidAppointments = (int) ($eventTotals['appointment_paid'] ?? 0);
            $completedSessions = (int) ($eventTotals['session_completed'] ?? 0);

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
                    'appointments' => $appointments,
                    'first_appointments' => (int) ($eventTotals['appointment_booked'] ?? 0),
                    'repeat_appointments' => (int) ($eventTotals['repeat_appointment_booked'] ?? 0),
                    'paid_appointments' => $paidAppointments,
                    'payments_completed' => (int) ($eventTotals['payment_completed'] ?? 0),
                    'sessions_completed' => $completedSessions,
                    'raw_profile_views' => (int) ($rawTotals['profile_view'] ?? 0),
                ],
                'rates' => [
                    'lead_conversion' => $profileViews > 0 ? round(($leads / $profileViews) * 100, 2) : 0,
                    'form_conversion' => $profileViews > 0 ? round(($leadSubmits / $profileViews) * 100, 2) : 0,
                    'contact_ctr' => $profileViews > 0 ? round(($contactClicks / $profileViews) * 100, 2) : 0,
                    'lead_to_appointment' => $leads > 0 ? round(($appointments / $leads) * 100, 2) : 0,
                    'appointment_to_paid' => $appointments > 0 ? round(($paidAppointments / $appointments) * 100, 2) : 0,
                    'appointment_to_completed' => $appointments > 0 ? round(($completedSessions / $appointments) * 100, 2) : 0,
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
            'appointments' => $professionals->sum(fn ($row) => $row['totals']['appointments']),
            'paid_appointments' => $professionals->sum(fn ($row) => $row['totals']['paid_appointments']),
            'sessions_completed' => $professionals->sum(fn ($row) => $row['totals']['sessions_completed']),
            'lead_conversion' => $professionals->sum(fn ($row) => $row['totals']['profile_views']) > 0
                ? round(($professionals->sum(fn ($row) => $row['totals']['leads']) / $professionals->sum(fn ($row) => $row['totals']['profile_views'])) * 100, 2)
                : 0,
        ];

        $growth = $this->buildGrowthAnalytics($from, $to, $granularity, $leadStatus, $activeLeadStatuses);

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
                'growth' => $growth,
            ],
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'only_activity' => $request->boolean('only_activity', true),
                'lead_status' => $leadStatus,
                'granularity' => $granularity,
            ],
        ]);
    }

    private function buildGrowthAnalytics(
        Carbon $from,
        Carbon $to,
        string $granularity,
        ?string $leadStatus,
        array $activeLeadStatuses
    ): array {
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days)->addSecond()->startOfDay();

        $leadQuery = fn (Carbon $start, Carbon $end) => ConsultaContacto::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($leadStatus === 'active', fn ($query) => $query->whereIn('status', $activeLeadStatuses));

        $currentLeads = $leadQuery($from, $to)->count();
        $previousLeads = $leadQuery($previousFrom, $previousTo)->count();
        $currentRegistrations = User::query()->whereBetween('created_at', [$from, $to])->count();
        $previousRegistrations = User::query()->whereBetween('created_at', [$previousFrom, $previousTo])->count();
        $currentActiveRegistrations = User::query()->where('activo', true)->whereBetween('created_at', [$from, $to])->count();
        $previousActiveRegistrations = User::query()->where('activo', true)->whereBetween('created_at', [$previousFrom, $previousTo])->count();
        $currentPatientRegistrations = Patient::query()->whereBetween('created_at', [$from, $to])->count();
        $previousPatientRegistrations = Patient::query()->whereBetween('created_at', [$previousFrom, $previousTo])->count();

        $leadDates = $leadQuery($from, $to)->pluck('created_at');
        $registrationRows = User::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'activo']);
        $patientRegistrationDates = Patient::query()
            ->whereBetween('created_at', [$from, $to])
            ->pluck('created_at');
        $eventRows = ProfessionalAnalyticsEvent::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('event_type', ['profile_view', 'phone_click', 'whatsapp_click', 'facebook_click', 'instagram_click', 'linkedin_click', 'website_click'])
            ->get(['created_at', 'event_type']);

        $buckets = $this->growthBuckets($from, $to, $granularity);
        $leadCounts = $leadDates->countBy(fn ($date) => $this->bucketKey(Carbon::parse($date), $granularity));
        $registrationCounts = $registrationRows->countBy(fn ($row) => $this->bucketKey($row->created_at, $granularity));
        $activeRegistrationCounts = $registrationRows->where('activo', true)->countBy(fn ($row) => $this->bucketKey($row->created_at, $granularity));
        $patientRegistrationCounts = $patientRegistrationDates->countBy(fn ($date) => $this->bucketKey(Carbon::parse($date), $granularity));
        $viewCounts = $eventRows->where('event_type', 'profile_view')->countBy(fn ($row) => $this->bucketKey($row->created_at, $granularity));
        $contactCounts = $eventRows->where('event_type', '!=', 'profile_view')->countBy(fn ($row) => $this->bucketKey($row->created_at, $granularity));

        $registeredRunning = User::query()->where('created_at', '<', $from)->count();
        $activeRunning = User::query()->where('activo', true)->where('created_at', '<', $from)->count();
        $patientRunning = Patient::query()->where('created_at', '<', $from)->count();
        $series = $buckets->map(function (Carbon $bucket) use ($granularity, $leadCounts, $registrationCounts, $activeRegistrationCounts, $patientRegistrationCounts, $viewCounts, $contactCounts, &$registeredRunning, &$activeRunning, &$patientRunning) {
            $key = $this->bucketKey($bucket, $granularity);
            $registrations = (int) ($registrationCounts[$key] ?? 0);
            $activeRegistrations = (int) ($activeRegistrationCounts[$key] ?? 0);
            $patientRegistrations = (int) ($patientRegistrationCounts[$key] ?? 0);
            $registeredRunning += $registrations;
            $activeRunning += $activeRegistrations;
            $patientRunning += $patientRegistrations;

            return [
                'date' => $key,
                'label' => $this->bucketLabel($bucket, $granularity),
                'leads' => (int) ($leadCounts[$key] ?? 0),
                'psychologists_registered' => $registrations,
                'psychologists_active' => $activeRegistrations,
                'patients_registered' => $patientRegistrations,
                'registered_total' => $registeredRunning,
                'active_total' => $activeRunning,
                'patients_total' => $patientRunning,
                'profile_views' => (int) ($viewCounts[$key] ?? 0),
                'contact_clicks' => (int) ($contactCounts[$key] ?? 0),
            ];
        })->values();

        $totalRegistered = User::query()->count();
        $totalActive = User::query()->where('activo', true)->count();
        $totalVisible = User::query()->publiclyVisible()->count();
        $totalPatients = Patient::query()->count();
        $leadStatuses = $leadQuery($from, $to)
            ->selectRaw("COALESCE(status, 'sin_estado') as status, COUNT(*) as total")
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'total' => (int) $row->total])
            ->values();

        return [
            'granularity' => $granularity,
            'series' => $series,
            'totals' => [
                'registered' => $totalRegistered,
                'active' => $totalActive,
                'visible' => $totalVisible,
                'patients' => $totalPatients,
                'activation_rate' => $totalRegistered > 0 ? round(($totalActive / $totalRegistered) * 100, 1) : 0,
                'visibility_rate' => $totalRegistered > 0 ? round(($totalVisible / $totalRegistered) * 100, 1) : 0,
            ],
            'changes' => [
                'leads' => $this->percentageChange($currentLeads, $previousLeads),
                'psychologists_registered' => $this->percentageChange($currentRegistrations, $previousRegistrations),
                'psychologists_active' => $this->percentageChange($currentActiveRegistrations, $previousActiveRegistrations),
                'patients_registered' => $this->percentageChange($currentPatientRegistrations, $previousPatientRegistrations),
            ],
            'period' => [
                'leads' => $currentLeads,
                'psychologists_registered' => $currentRegistrations,
                'psychologists_active' => $currentActiveRegistrations,
                'patients_registered' => $currentPatientRegistrations,
            ],
            'lead_statuses' => $leadStatuses,
        ];
    }

    private function resolveGranularity(Request $request, Carbon $from, Carbon $to): string
    {
        $requested = $request->query('granularity', 'auto');
        if (in_array($requested, ['day', 'week', 'month'], true)) {
            return $requested;
        }

        $days = $from->diffInDays($to) + 1;

        return $days <= 62 ? 'day' : ($days <= 240 ? 'week' : 'month');
    }

    private function growthBuckets(Carbon $from, Carbon $to, string $granularity)
    {
        $start = match ($granularity) {
            'week' => $from->copy()->startOfWeek(),
            'month' => $from->copy()->startOfMonth(),
            default => $from->copy()->startOfDay(),
        };
        $interval = match ($granularity) {
            'week' => '1 week',
            'month' => '1 month',
            default => '1 day',
        };

        return collect(CarbonPeriod::create($start, $interval, $to))->map(fn ($date) => Carbon::instance($date));
    }

    private function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function bucketLabel(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => 'Sem '.$date->copy()->startOfWeek()->format('d M'),
            'month' => $date->translatedFormat('M Y'),
            default => $date->format('d M'),
        };
    }

    private function percentageChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function track(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'event_type' => 'required|string|in:'.implode(',', self::ALLOWED_EVENTS),
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
            ? hash('sha256', $request->ip().'|'.config('app.key'))
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
                    'appointments' => (int) ($eventCounts['appointment_booked'] ?? 0) + (int) ($eventCounts['repeat_appointment_booked'] ?? 0),
                    'first_appointments' => (int) ($eventCounts['appointment_booked'] ?? 0),
                    'repeat_appointments' => (int) ($eventCounts['repeat_appointment_booked'] ?? 0),
                    'paid_appointments' => (int) ($eventCounts['appointment_paid'] ?? 0),
                    'payments_completed' => (int) ($eventCounts['payment_completed'] ?? 0),
                    'sessions_completed' => (int) ($eventCounts['session_completed'] ?? 0),
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
