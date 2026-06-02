<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequestRequest;
use App\Models\CampaignRequest;
use App\Models\MarketingPackage;
use App\Services\MarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function __construct(
        private readonly MarketingService $marketingService
    ) {}

    /**
     * GET /api/marketing/packages
     *
     * Lista todos los paquetes de marketing activos disponibles para el psicólogo.
     */
    public function index(Request $request): JsonResponse
    {
        $packages = MarketingPackage::active()
            ->with(['groupCampaigns' => fn($q) => $q->whereIn('status', ['recruiting', 'full'])->latest()])
            ->orderBy('type')
            ->orderBy('price')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'type',
                'price',
                'max_slots',
            ]);

        // Añade current_slots por paquete y oculta la relación del response
        $packages->each(function ($pkg) use ($request) {
            if ($pkg->type->value === 'group') {
                $active = $pkg->groupCampaigns->first();
                $pkg->current_slots = $active?->current_slots ?? 0;
            } else {
                $pkg->current_slots = null;
            }

            $blockingCampaign = $this->marketingService->blockingCampaignForPackage($pkg, $request->user()->id);
            $pkg->can_purchase = $blockingCampaign === null;
            $pkg->purchase_block_reason = null;

            if ($blockingCampaign) {
                $pkg->purchase_block_reason = $pkg->type->value === 'group'
                    ? 'Ya solicitaste este paquete CombiMindMeet.'
                    : 'Ya tienes este paquete individual vigente.';
            }

            $pkg->makeHidden('groupCampaigns');
        });

        return response()->json([
            'data' => $packages,
        ]);
    }

    /**
     * POST /api/marketing/campaign-requests
     *
     * El psicólogo autenticado crea una nueva solicitud de campaña.
     * La validación corre a través de StoreCampaignRequestRequest.
     * La lógica de negocio (asignación de CombiMindMeet) está en MarketingService.
     */
    public function store(StoreCampaignRequestRequest $request): JsonResponse
    {
        $campaignRequest = $this->marketingService->createCampaignRequest(
            $request->validated(),
            $request->user()->id
        );

        $campaignRequest->load(['marketingPackage', 'groupCampaign']);

        return response()->json([
            'message' => 'Solicitud de campaña creada exitosamente.',
            'data'    => $campaignRequest,
        ], 201);
    }

    /**
     * GET /api/marketing/my-campaigns
     *
     * Lista las solicitudes de campaña del psicólogo autenticado.
     */
    public function my(Request $request): JsonResponse
    {
        $campaigns = CampaignRequest::with([
            'marketingPackage:id,name,type,price,max_slots',
            'groupCampaign.marketingPackage:id,name,max_slots',
        ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'user_id', 'marketing_package_id', 'group_campaign_id', 'status', 'target_audience', 'locations', 'starts_at', 'ends_at', 'campaign_url', 'created_at']);

        return response()->json([
            'data' => $campaigns->map(fn(CampaignRequest $campaign) => $this->campaignPayload($campaign)),
        ]);
    }

    private function campaignPayload(CampaignRequest $campaign): array
    {
        $group = $campaign->groupCampaign;
        $maxSlots = (int) ($group?->marketingPackage?->max_slots ?? $campaign->marketingPackage?->max_slots ?? 0);
        $paidSlots = $group
            ? $group->campaignRequests()
                ->whereIn('status', ['paid', 'active'])
                ->count()
            : null;

        return [
            'id' => $campaign->id,
            'user_id' => $campaign->user_id,
            'marketing_package_id' => $campaign->marketing_package_id,
            'group_campaign_id' => $campaign->group_campaign_id,
            'status' => $campaign->status?->value,
            'target_audience' => $campaign->target_audience,
            'locations' => $campaign->locations,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'campaign_url' => $campaign->campaign_url,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'group_campaign' => $group ? [
                'id' => $group->id,
                'status' => $group->status?->value,
                'paid_slots' => $paidSlots,
                'max_slots' => $maxSlots,
                'missing_slots' => max($maxSlots - (int) $paidSlots, 0),
                'is_full' => $maxSlots > 0 && (int) $paidSlots >= $maxSlots,
            ] : null,
            'marketing_package' => $campaign->marketingPackage ? [
                'id' => $campaign->marketingPackage->id,
                'name' => $campaign->marketingPackage->name,
                'type' => $campaign->marketingPackage->type->value,
                'price' => $campaign->marketingPackage->price,
                'max_slots' => $campaign->marketingPackage->max_slots,
            ] : null,
        ];
    }
}
