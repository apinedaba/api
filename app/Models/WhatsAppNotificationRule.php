<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppNotificationRule extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_notification_rules';

    protected $fillable = [
        'event_key',
        'label',
        'description',
        'channels',
        'whatsapp_template_key',
        'email_subject',
        'email_body',
        'sms_body',
        'is_active',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_active' => 'boolean',
    ];

    public function sendsTo(string $channel): bool
    {
        return $this->is_active && in_array($channel, $this->channels ?? [], true);
    }
}
