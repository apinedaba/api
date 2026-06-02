<?php

namespace App\Services;

use App\Enums\CampaignRequestStatus;
use App\Enums\GroupCampaignStatus;
use App\Enums\MarketingPackageType;
use App\Models\CampaignRequest;
use App\Models\GroupCampaign;
use App\Models\MarketingPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingService
{
    /**
     * Crea una solicitud de campaña para el psicólogo autenticado.
     *
     * Si el paquete es de tipo 'group':
     *   1. Busca una GroupCampaign en estatus 'recruiting' con slots disponibles.
     *   2. Si no existe ninguna, crea una nueva.
     *   3. Incrementa el contador de slots. Si se llenó, pasa la campaña a 'full'.
     *
     * @param  array{marketing_package_id: int, target_audience?: array, locations?: array} $data
     * @param  int $userId  ID del usuario (psicólogo) autenticado
     * @return CampaignRequest
     *
     * @throws \Illuminate\Validation\ValidationException  Si el paquete está inactivo.
     */
    public function createCampaignRequest(array $data, int $userId): CampaignRequest
    {
        /** @var MarketingPackage $package */
        $package = MarketingPackage::findOrFail($data['marketing_package_id']);

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'marketing_package_id' => ['El paquete de marketing seleccionado no está disponible.'],
            ]);
        }

        if (! $this->canCreateCampaignRequest($package, $userId)) {
            $message = $package->type === MarketingPackageType::Group
                ? 'Ya solicitaste este paquete CombiMindMeet. Podrás solicitarlo otra vez cuando finalice o se cancele.'
                : 'Ya tienes este paquete individual vigente. Podrás renovarlo cuando finalice.';

            throw ValidationException::withMessages([
                'campaign' => [$message],
            ]);
        }

        return DB::transaction(function () use ($data, $userId, $package): CampaignRequest {
            $groupCampaignId = null;

            if ($package->type === MarketingPackageType::Group) {
                $groupCampaignId = $this->resolveGroupCampaign($package)->id;
            }

            return CampaignRequest::create([
                'user_id'               => $userId,
                'marketing_package_id'  => $package->id,
                'group_campaign_id'     => $groupCampaignId,
                'target_audience'       => $data['target_audience'] ?? null,
                'locations'             => $data['locations'] ?? null,
                'status'                => CampaignRequestStatus::PendingPayment->value,
            ]);
        });
    }

    /**
     * Busca o crea la GroupCampaign adecuada y devuelve la instancia.
     *
     * NOTA: el incremento de 'current_slots' ocurre en MarketingPaymentService
     * cuando Stripe confirma el pago (checkout.session.completed).
     * Aquí solo reservamos la asociación con la campaña grupal.
     */
    private function resolveGroupCampaign(MarketingPackage $package): GroupCampaign
    {
        // Bloqueo pesimista para evitar race conditions en alta concurrencia.
        $groupCampaign = GroupCampaign::where('marketing_package_id', $package->id)
            ->where('status', GroupCampaignStatus::Recruiting->value)
            ->lockForUpdate()
            ->first();

        if (! $groupCampaign || ! $groupCampaign->hasAvailableSlots()) {
            $groupCampaign = GroupCampaign::create([
                'marketing_package_id' => $package->id,
                'current_slots'        => 0,
                'status'               => GroupCampaignStatus::Recruiting->value,
            ]);
        }

        return $groupCampaign;
    }

    public function canCreateCampaignRequest(MarketingPackage $package, int $userId): bool
    {
        return ! CampaignRequest::where('user_id', $userId)
            ->where('marketing_package_id', $package->id)
            ->whereIn('status', [
                CampaignRequestStatus::PendingPayment->value,
                CampaignRequestStatus::Paid->value,
                CampaignRequestStatus::Active->value,
            ])
            ->exists();
    }

    public function blockingCampaignForPackage(MarketingPackage $package, int $userId): ?CampaignRequest
    {
        return CampaignRequest::with('marketingPackage:id,name,type,price')
            ->where('user_id', $userId)
            ->where('marketing_package_id', $package->id)
            ->whereIn('status', [
                CampaignRequestStatus::PendingPayment->value,
                CampaignRequestStatus::Paid->value,
                CampaignRequestStatus::Active->value,
            ])
            ->latest()
            ->first();
    }
}
