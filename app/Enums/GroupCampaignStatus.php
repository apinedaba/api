<?php

namespace App\Enums;

enum GroupCampaignStatus: string
{
    case Recruiting = 'recruiting';
    case Full       = 'full';
    case Active     = 'active';
    case Completed  = 'completed';
}
