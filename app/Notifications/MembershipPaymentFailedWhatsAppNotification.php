<?php

namespace App\Notifications;

use App\Support\ProfessionalContact;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MembershipPaymentFailedWhatsAppNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'message_type' => 'template',
            'phone' => ProfessionalContact::whatsapp($notifiable),
            'template' => config('services.whatsapp.templates.membership_payment_failed', 'pago_fallido'),
            'language' => 'es_MX',
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => ProfessionalContact::publicName($notifiable)],
                    ],
                ],
            ],
            'context' => [
                'user_id' => $notifiable->id,
                'event' => 'membership_payment_failed',
            ],
        ];
    }
}
