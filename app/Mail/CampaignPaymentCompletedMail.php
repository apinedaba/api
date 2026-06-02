<?php

namespace App\Mail;

use App\Models\CampaignRequest;
use Illuminate\Mail\Mailable;

class CampaignPaymentCompletedMail extends Mailable
{
    public function __construct(
        private readonly CampaignRequest $campaignRequest
    ) {}

    public function build()
    {
        $user = $this->campaignRequest->user;
        $package = $this->campaignRequest->marketingPackage;
        $targetAudience = $this->campaignRequest->target_audience;
        $locations = $this->campaignRequest->locations;

        return $this->subject('✅ Recibimos tu pago de campaña - MindMeet')
            ->view('email.campaign-payment-completed')
            ->with([
                'user' => $user,
                'package' => $package,
                'campaignRequest' => $this->campaignRequest,
                'targetAudience' => $targetAudience,
                'locations' => $locations,
                'dashboardUrl' => rtrim(config('app.front_url_psicologo') ?: config('app.url'), '/') . '/marketing',
            ]);
    }
}
