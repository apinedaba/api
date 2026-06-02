<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\CampaignRequestStatus;
use App\Enums\GroupCampaignStatus;
use App\Mail\CampaignActivatedMail;
use App\Mail\CampaignFinishedMail;
use App\Models\CampaignRequest;
use App\Models\GroupCampaign;
use App\Models\MarketingPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketingController extends Controller
{
    // ── Paquetes ──────────────────────────────────────────────────────────────

    public function packages(): Response
    {
        $packages = MarketingPackage::latest()->get();

        return Inertia::render('Marketing/PackageManagement', [
            'packages' => $packages,
        ]);
    }

    public function storePackage(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name'              => ['required', 'string', 'max:150'],
                'slug'              => ['required', 'string', 'max:150', 'unique:marketing_packages,slug'],
                'description'       => ['nullable', 'string'],
                'type'              => ['required', Rule::in(['individual', 'group'])],
                'price'             => ['required', 'numeric', 'min:50', 'max:999999'], // Mínimo $50 MXN
                'max_slots'         => ['nullable', 'integer', 'min:2', 'required_if:type,group'],
                'stripe_product_id' => ['nullable', 'string', 'max:100'],
                'is_active'         => ['boolean'],
            ]);

            MarketingPackage::create($validated);

            return back()->with('success', 'Paquete creado correctamente.');
        } catch (\Exception $e) {
            \Log::error('Error creando paquete marketing', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error al crear paquete. Intenta de nuevo.');
        }
    }

    public function updatePackage(Request $request, MarketingPackage $package): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name'              => ['required', 'string', 'max:150'],
                'slug'              => ['required', 'string', 'max:150', Rule::unique('marketing_packages', 'slug')->ignore($package->id)],
                'description'       => ['nullable', 'string'],
                'type'              => ['required', Rule::in(['individual', 'group'])],
                'price'             => ['required', 'numeric', 'min:50', 'max:999999'], // Mínimo $50 MXN
                'max_slots'         => ['nullable', 'integer', 'min:2', 'required_if:type,group'],
                'stripe_product_id' => ['nullable', 'string', 'max:100'],
                'is_active'         => ['boolean'],
            ]);

            $package->update($validated);

            return back()->with('success', 'Paquete actualizado correctamente.');
        } catch (\Exception $e) {
            \Log::error('Error actualizando paquete marketing', ['error' => $e->getMessage(), 'package_id' => $package->id]);
            return back()->with('error', 'Error al actualizar paquete. Intenta de nuevo.');
        }
    }

    // ── Campañas ──────────────────────────────────────────────────────────────

    public function campaigns(): Response
    {
        $requests = CampaignRequest::with([
            'user:id,name,email,image',
            'marketingPackage:id,name,type,price',
        ])
            ->whereIn('status', ['paid', 'active', 'finished'])
            ->whereNull('group_campaign_id')
            ->latest()
            ->get()
            ->map(fn(CampaignRequest $req) => [
                'id'               => $req->id,
                'status'           => $req->status->value,
                'target_audience'  => $req->target_audience,
                'locations'        => $req->locations,
                'created_at'       => $req->created_at?->format('d/m/Y'),
                'starts_at'        => $req->starts_at?->format('Y-m-d'),
                'ends_at'          => $req->ends_at?->format('Y-m-d'),
                'campaign_url'     => $req->campaign_url,
                'user'             => $req->user ? [
                    'id'    => $req->user->id,
                    'name'  => $req->user->name,
                    'email' => $req->user->email,
                    'image' => $req->user->image,
                ] : null,
                'marketing_package' => $req->marketingPackage ? [
                    'id'    => $req->marketingPackage->id,
                    'name'  => $req->marketingPackage->name,
                    'type'  => $req->marketingPackage->type->value,
                    'price' => $req->marketingPackage->price,
                ] : null,
                'group_campaign_id' => null,
            ]);

        $groups = GroupCampaign::with([
            'marketingPackage:id,name,max_slots,price',
            'campaignRequests' => fn($q) => $q
                ->with(['user:id,name,email,image'])
                ->whereIn('status', ['paid', 'active', 'finished'])
                ->latest(),
        ])
            ->withCount([
                'campaignRequests as paid_slots' => fn($q) => $q->whereIn('status', ['paid', 'active']),
            ])
            ->whereIn('status', ['recruiting', 'full', 'active', 'completed'])
            ->latest()
            ->get()
            ->map(function (GroupCampaign $g) {
                $maxSlots = (int) ($g->marketingPackage?->max_slots ?? 0);
                $paidSlots = (int) $g->paid_slots;

                return [
                    'id'             => $g->id,
                    'status'         => $g->status->value,
                    'current_slots'  => $g->current_slots,
                    'paid_slots'     => $paidSlots,
                    'missing_slots'  => max($maxSlots - $paidSlots, 0),
                    'can_activate'   => $maxSlots > 0 && $paidSlots >= $maxSlots && in_array($g->status->value, ['full', 'active'], true),
                    'created_at'     => $g->created_at?->format('d/m/Y'),
                    'package'        => $g->marketingPackage ? [
                        'id'        => $g->marketingPackage->id,
                        'name'      => $g->marketingPackage->name,
                        'max_slots' => $g->marketingPackage->max_slots,
                        'price'     => $g->marketingPackage->price,
                    ] : null,
                    'members'        => $g->campaignRequests->map(fn(CampaignRequest $req) => [
                        'id'              => $req->id,
                        'status'          => $req->status->value,
                        'target_audience' => $req->target_audience,
                        'locations'       => $req->locations,
                        'created_at'      => $req->created_at?->format('d/m/Y'),
                        'starts_at'       => $req->starts_at?->format('Y-m-d'),
                        'ends_at'         => $req->ends_at?->format('Y-m-d'),
                        'campaign_url'    => $req->campaign_url,
                        'user'            => $req->user ? [
                            'id'    => $req->user->id,
                            'name'  => $req->user->name,
                            'email' => $req->user->email,
                            'image' => $req->user->image,
                        ] : null,
                        'marketing_package' => $g->marketingPackage ? [
                            'id'    => $g->marketingPackage->id,
                            'name'  => $g->marketingPackage->name,
                            'type'  => 'group',
                            'price' => $g->marketingPackage->price,
                        ] : null,
                        'group_campaign_id' => $g->id,
                    ]),
                ];
            });

        return Inertia::render('Marketing/CampaignDashboard', [
            'requests' => $requests,
            'groups'   => $groups,
        ]);
    }

    public function activateCampaign(Request $request, CampaignRequest $campaignRequest): RedirectResponse
    {
        $validated = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'campaign_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if (! in_array($campaignRequest->status?->value, [
            CampaignRequestStatus::Paid->value,
            CampaignRequestStatus::Active->value,
        ], true)) {
            return back()->with('error', 'Solo puedes activar campañas pagadas.');
        }

        if ($campaignRequest->groupCampaign) {
            return back()->with('error', 'Activa las campañas CombiMindMeet desde la tarjeta del grupo.');
        }

        [$startsAt, $endsAt] = $this->resolveRunDates($validated);

        $this->markCampaignActive($campaignRequest, $startsAt, $endsAt, $validated['campaign_url'] ?? null);

        return back()->with('success', 'Campaña activada correctamente.');
    }

    public function activateGroupCampaign(Request $request, GroupCampaign $groupCampaign): RedirectResponse
    {
        $validated = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'campaign_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $groupCampaign->load(['marketingPackage', 'campaignRequests.user', 'campaignRequests.marketingPackage']);

        $maxSlots = (int) ($groupCampaign->marketingPackage?->max_slots ?? 0);
        $paidCampaigns = $groupCampaign->campaignRequests
            ->filter(fn(CampaignRequest $request) => $request->status === CampaignRequestStatus::Paid)
            ->values();

        $activeCampaigns = $groupCampaign->campaignRequests
            ->filter(fn(CampaignRequest $request) => $request->status === CampaignRequestStatus::Active)
            ->values();

        if ($maxSlots <= 0 || ($paidCampaigns->count() + $activeCampaigns->count()) < $maxSlots) {
            return back()->with('error', 'Las campañas CombiMindMeet solo se pueden publicar cuando se llenan todos los espacios.');
        }

        [$startsAt, $endsAt] = $this->resolveRunDates($validated);

        foreach ($paidCampaigns as $campaignRequest) {
            $this->markCampaignActive($campaignRequest, $startsAt, $endsAt, $validated['campaign_url'] ?? null);
        }

        $groupCampaign->update(['status' => GroupCampaignStatus::Active->value]);

        return back()->with('success', 'CombiMindMeet activada correctamente.');
    }

    public function finishGroupCampaign(GroupCampaign $groupCampaign): RedirectResponse
    {
        $groupCampaign->load(['campaignRequests.user', 'campaignRequests.marketingPackage']);

        $groupCampaign->campaignRequests
            ->filter(fn(CampaignRequest $request) => $request->status === CampaignRequestStatus::Active)
            ->each(fn(CampaignRequest $campaignRequest) => $this->markCampaignFinished($campaignRequest));

        $groupCampaign->update(['status' => GroupCampaignStatus::Completed->value]);

        return back()->with('success', 'CombiMindMeet finalizada correctamente.');
    }

    public function updateCampaignLink(Request $request, CampaignRequest $campaignRequest): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $campaignRequest->update([
            'campaign_url' => $validated['campaign_url'] ?? null,
        ]);

        return back()->with('success', 'Link de campaña actualizado correctamente.');
    }

    public function updateCampaignBrief(Request $request, CampaignRequest $campaignRequest): RedirectResponse
    {
        $validated = $request->validate([
            'target_audience' => ['nullable', 'array'],
            'target_audience.age_range' => ['nullable', 'string', 'max:50'],
            'target_audience.gender' => ['nullable', Rule::in(['femenino', 'masculino', 'todos'])],
            'target_audience.specialty_focus' => ['nullable', 'string', 'max:150'],
            'target_audience.interests' => ['nullable', 'array'],
            'target_audience.interests.*' => ['string', 'max:100'],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['string', 'max:100'],
        ]);

        $campaignRequest->update([
            'target_audience' => $validated['target_audience'] ?? [],
            'locations' => $validated['locations'] ?? [],
        ]);

        return back()->with('success', 'Brief de campaña actualizado correctamente.');
    }

    public function finishCampaign(CampaignRequest $campaignRequest): RedirectResponse
    {
        if ($campaignRequest->groupCampaign) {
            return back()->with('error', 'Finaliza las campañas CombiMindMeet desde la tarjeta del grupo.');
        }

        if ($campaignRequest->status !== CampaignRequestStatus::Active) {
            return back()->with('error', 'Solo puedes finalizar campañas activas.');
        }

        $campaignRequest->update([
            'status' => CampaignRequestStatus::Finished->value,
            'ends_at' => now(),
        ]);

        $this->sendCampaignFinishedMail($campaignRequest);

        return back()->with('success', 'Campaña finalizada correctamente.');
    }

    private function resolveRunDates(array $validated): array
    {
        $startsAt = isset($validated['starts_at'])
            ? \Carbon\Carbon::parse($validated['starts_at'])->startOfDay()
            : now();

        $endsAt = isset($validated['ends_at'])
            ? \Carbon\Carbon::parse($validated['ends_at'])->endOfDay()
            : $startsAt->copy()->addDays((int) ($validated['duration_days'] ?? 30))->endOfDay();

        return [$startsAt, $endsAt];
    }

    private function markCampaignActive(CampaignRequest $campaignRequest, $startsAt, $endsAt, ?string $campaignUrl): void
    {
        $campaignRequest->update([
            'status' => CampaignRequestStatus::Active->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'campaign_url' => $campaignUrl ?: $campaignRequest->campaign_url,
        ]);

        $campaignRequest->loadMissing(['user', 'marketingPackage']);

        if (! $campaignRequest->user?->email) {
            return;
        }

        try {
            Mail::to($campaignRequest->user->email)
                ->send(new CampaignActivatedMail($campaignRequest));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de activación MindBoost.', [
                'campaign_request_id' => $campaignRequest->id,
                'user_email' => $campaignRequest->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markCampaignFinished(CampaignRequest $campaignRequest): void
    {
        $campaignRequest->update([
            'status' => CampaignRequestStatus::Finished->value,
            'ends_at' => now(),
        ]);

        $this->sendCampaignFinishedMail($campaignRequest);
    }

    private function sendCampaignFinishedMail(CampaignRequest $campaignRequest): void
    {
        $campaignRequest->loadMissing(['user', 'marketingPackage']);

        if (! $campaignRequest->user?->email) {
            return;
        }

        try {
            Mail::to($campaignRequest->user->email)
                ->send(new CampaignFinishedMail($campaignRequest));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de finalización MindBoost.', [
                'campaign_request_id' => $campaignRequest->id,
                'user_email' => $campaignRequest->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
