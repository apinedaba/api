<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppNotificationRule;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminWhatsAppAutomationController extends Controller
{
    public function index(): Response
    {
        $monthStart = now()->startOfMonth();

        return Inertia::render('WhatsAppAutomation', [
            'templates' => WhatsAppTemplate::query()->orderBy('key')->get(),
            'rules' => WhatsAppNotificationRule::query()->orderBy('event_key')->get(),
            'metrics' => [
                'total' => WhatsAppMessage::query()->count(),
                'month' => WhatsAppMessage::query()->where('created_at', '>=', $monthStart)->count(),
                'sent_total' => WhatsAppMessage::query()->whereIn('status', ['sent', 'delivered', 'read'])->count(),
                'failed_total' => WhatsAppMessage::query()->where('status', 'failed')->count(),
                'by_status' => $this->countsBy('status'),
                'by_template' => $this->countsBy('template'),
                'month_by_template' => $this->countsBy('template', $monthStart),
            ],
            'fallbacks' => config('services.whatsapp.templates', []),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        WhatsAppTemplate::create($this->validateTemplate($request));

        return back()->with('success', 'Template WhatsApp creado.');
    }

    public function updateTemplate(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $template->update($this->validateTemplate($request, $template));

        return back()->with('success', 'Template WhatsApp actualizado.');
    }

    public function destroyTemplate(WhatsAppTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template WhatsApp eliminado.');
    }

    public function updateRule(Request $request, WhatsAppNotificationRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['database', 'email', 'sms', 'whatsapp'])],
            'whatsapp_template_key' => ['nullable', 'string', 'max:100'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string'],
            'sms_body' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['channels'] = array_values($data['channels'] ?? []);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $rule->update($data);

        return back()->with('success', 'Regla de notificacion actualizada.');
    }

    protected function validateTemplate(Request $request, ?WhatsAppTemplate $template = null): array
    {
        return $request->validate([
            'key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('whatsapp_templates', 'key')->ignore($template?->id),
            ],
            'template_name' => ['required', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'body_parameters' => ['nullable', 'array'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function countsBy(string $column, mixed $since = null): array
    {
        return WhatsAppMessage::query()
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->{$column} ?: 'sin_valor',
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }
}
