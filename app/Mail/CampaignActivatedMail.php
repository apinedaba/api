<?php

namespace App\Mail;

use App\Models\CampaignRequest;
use Illuminate\Mail\Mailable;

class CampaignActivatedMail extends Mailable
{
    public function __construct(
        private readonly CampaignRequest $campaignRequest
    ) {}

    public function build()
    {
        $campaign = $this->campaignRequest->loadMissing(['user', 'marketingPackage']);

        return $this->subject('🚀 Tu campaña MindBoost ya está activa')
            ->view('email.campaign-activated')
            ->with([
                'user' => $campaign->user,
                'package' => $campaign->marketingPackage,
                'campaignRequest' => $campaign,
                'dashboardUrl' => rtrim(config('app.front_url_psicologo') ?: config('app.url'), '/') . '/marketing',
            ]);
    }
}
