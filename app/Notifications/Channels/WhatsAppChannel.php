<?php

namespace App\Notifications\Channels;

use App\Jobs\SendWhatsAppMessageJob;
use App\Support\PhoneNormalizer;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (! is_array($message) || empty($message['phone'])) {
            return;
        }

        try {
            $message['phone'] = PhoneNormalizer::toE164((string) $message['phone']);
        } catch (InvalidArgumentException $exception) {
            Log::channel('whatsapp')->warning('WhatsApp notification skipped: invalid phone', [
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->id ?? null,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $message['context'] = array_merge($message['context'] ?? [], $this->contextFor($notifiable));

        SendWhatsAppMessageJob::dispatch($message);
    }

    protected function contextFor(object $notifiable): array
    {
        return match ($notifiable::class) {
            \App\Models\User::class => ['user_id' => $notifiable->id],
            \App\Models\Patient::class => ['patient_id' => $notifiable->id],
            default => [],
        };
    }
}
