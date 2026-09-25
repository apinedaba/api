<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicAudienceResponse extends Model
{
    protected $fillable = [
        'session_id',
        'audience',
        'route_choice',
        'destination',
        'landing_page',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'ip_hash',
    ];
}
