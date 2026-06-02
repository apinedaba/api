<?php

namespace App\Enums;

enum CampaignRequestStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid           = 'paid';
    case Active         = 'active';
    case Finished       = 'finished';
    case Cancelled      = 'cancelled';
}
