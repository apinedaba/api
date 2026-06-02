<?php

namespace App\Console\Commands;

use App\Enums\CampaignRequestStatus;
use App\Enums\GroupCampaignStatus;
use App\Mail\CampaignFinishedMail;
use App\Models\CampaignRequest;
use App\Models\GroupCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExpireMarketingCampaigns extends Command
{
    protected $signature = 'marketing:expire-campaigns';

    protected $description = 'Finaliza campañas MindBoost activas cuya fecha de término ya pasó.';

    public function handle(): int
    {
        $campaigns = CampaignRequest::with(['user', 'marketingPackage', 'groupCampaign'])
            ->where('status', CampaignRequestStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        $groupIds = [];

        foreach ($campaigns as $campaign) {
            $this->finishCampaign($campaign);

            if ($campaign->group_campaign_id) {
                $groupIds[] = $campaign->group_campaign_id;
            }
        }

        GroupCampaign::whereIn('id', array_unique($groupIds))
            ->get()
            ->each(function (GroupCampaign $group) {
                $hasActiveCampaigns = $group->campaignRequests()
                    ->where('status', CampaignRequestStatus::Active->value)
                    ->exists();

                if (! $hasActiveCampaigns) {
                    $group->update(['status' => GroupCampaignStatus::Completed->value]);
                }
            });

        $this->info("Campañas finalizadas: {$campaigns->count()}");

        return Command::SUCCESS;
    }

    private function finishCampaign(CampaignRequest $campaign): void
    {
        $campaign->update([
            'status' => CampaignRequestStatus::Finished->value,
        ]);

        $campaign->refresh()->loadMissing(['user', 'marketingPackage']);

        if (! $campaign->user?->email) {
            return;
        }

        try {
            Mail::to($campaign->user->email)->send(new CampaignFinishedMail($campaign));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de finalización MindBoost.', [
                'campaign_request_id' => $campaign->id,
                'user_email' => $campaign->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
