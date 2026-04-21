<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinderGroup;
use App\Models\MinderMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMinderMetricsController extends Controller
{
    public function index(Request $request): Response
    {
        $days   = max(1, min((int) $request->integer('days', 30), 90));
        $from   = now()->subDays($days)->startOfDay();

        $messagesPerDay = MinderMessage::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->whereNull('parent_id')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $activeGroups = MinderGroup::withCount([
            'messages as messages_count' => fn($q) => $q->where('created_at', '>=', $from),
        ])
            ->orderByDesc('messages_count')
            ->limit(10)
            ->get(['id', 'name', 'avatar']);

        $totals = [
            'groups'   => MinderGroup::where('is_active', true)->count(),
            'messages' => MinderMessage::where('created_at', '>=', $from)->count(),
            'members'  => \App\Models\MinderGroupMember::count(),
        ];

        return Inertia::render('Minder/Metrics', [
            'messages_per_day' => $messagesPerDay,
            'active_groups'    => $activeGroups,
            'totals'           => $totals,
            'days'             => $days,
        ]);
    }
}
