<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedPregunta;
use App\Models\RedReport;
use App\Models\RedRespuesta;
use App\Notifications\RedContentModeratedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRedReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');

        $reports = RedReport::when($status !== 'all', fn($query) => $query->where('status', $status))
            ->with(['reporter:id,name,email', 'resolver:id,name'])
            ->latest()
            ->paginate(20)
            ->through(fn(RedReport $report) => $this->formatReport($report));

        return Inertia::render('Minder/ForumReports', [
            'reports' => $reports,
            'current_status' => $status,
        ]);
    }

    public function resolve(Request $request, RedReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:dismiss,hide,restore',
        ]);

        $target = $report->target();
        abort_if(! $target, 404, 'El contenido reportado ya no existe.');

        match ($validated['action']) {
            'hide' => $this->setVisibility($report, $target, false),
            'restore' => $this->setVisibility($report, $target, true),
            default => null,
        };

        $status = $validated['action'] === 'dismiss' ? 'dismissed' : 'resolved';
        $resolutionAction = match ($validated['action']) {
            'hide' => 'hidden',
            'restore' => 'restored',
            default => 'none',
        };
        $resolutionData = [
            'status' => $status,
            'resolution_action' => $resolutionAction,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ];

        if ($validated['action'] === 'dismiss') {
            $report->update($resolutionData);
        } else {
            RedReport::where('target_type', $report->target_type)
                ->where('target_id', $report->target_id)
                ->where('status', 'pending')
                ->update($resolutionData);
            $report->update($resolutionData);

            $target->autor?->notify(new RedContentModeratedNotification($target, $resolutionAction));
        }

        return back()->with('success', 'Reporte procesado.');
    }

    private function setVisibility(RedReport $report, RedPregunta|RedRespuesta $target, bool $visible): void
    {
        if ($report->target_type === RedReport::TARGET_QUESTION) {
            $target->update(['is_active' => $visible]);
            return;
        }

        $target->update(['is_deleted' => ! $visible]);
    }

    private function formatReport(RedReport $report): array
    {
        $target = $report->target();
        $author = $target?->autor()->first(['id', 'name', 'email']);

        return [
            ...$report->toArray(),
            'target' => $target ? [
                'id' => $target->id,
                'type' => $report->target_type,
                'title' => $target instanceof RedPregunta ? $target->titulo : $target->pregunta?->titulo,
                'content' => $target instanceof RedPregunta ? $target->descripcion : $target->contenido,
                'visible' => $target instanceof RedPregunta ? $target->is_active : ! $target->is_deleted,
                'author' => $author,
            ] : null,
        ];
    }
}
