<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinderBan;
use App\Models\MinderGroup;
use App\Models\MinderReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMinderReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');

        $reports = MinderReport::when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with([
                'message:id,body,group_id,user_id',
                'message.user:id,name,image',
                'message.group:id,name',
                'reporter:id,name',
                'resolver:id,name',
            ])
            ->latest()
            ->paginate(20);

        return Inertia::render('Minder/Reports', [
            'reports'        => $reports,
            'current_status' => $status,
        ]);
    }

    public function resolve(Request $request, MinderReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'action'     => 'required|in:resolve,dismiss,ban',
            'reason'     => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $report->update([
            'status'      => $validated['action'] === 'dismiss' ? 'dismissed' : 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        if ($validated['action'] === 'resolve') {
            $report->message->update(['is_deleted' => true]);
        }

        if ($validated['action'] === 'ban') {
            $report->message->update(['is_deleted' => true]);
            $group = MinderGroup::find($report->message->group_id);
            MinderBan::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $report->message->user_id],
                [
                    'banned_by'  => auth()->id(),
                    'reason'     => $validated['reason'] ?? 'Mensaje reportado',
                    'expires_at' => $validated['expires_at'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Reporte procesado.');
    }
}
