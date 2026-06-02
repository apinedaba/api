<?php

namespace App\Mail;

use App\Models\CampaignRequest;
use Illuminate\Mail\Mailable;

class CampaignPaymentFailedMail extends Mailable
{
    public function __construct(
        private readonly CampaignRequest $campaignRequest,
        private readonly string $errorMessage = ''
    ) {}

    public function build()
    {
        $user = $this->campaignRequest->user;
        $package = $this->campaignRequest->marketingPackage;

        return $this->subject('⚠️ Problema con tu campaña de marketing - MindMeet')
            ->view('email.campaign-payment-failed')
            ->with([
                'user' => $user,
                'package' => $package,
                'campaignRequest' => $this->campaignRequest,
                'errorMessage' => $this->errorMessage,
                'retryUrl' => rtrim(config('app.front_url_psicologo') ?: config('app.url'), '/') . '/marketing',
            ]);
    }
}
