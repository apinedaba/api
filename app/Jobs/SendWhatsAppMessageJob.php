<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected array $message)
    {
    }

    public function handle(WhatsAppService $whatsApp): void
    {
        $type = $this->message['message_type'] ?? $this->message['type'] ?? 'text';
        $context = $this->message['context'] ?? [];

        Log::channel('whatsapp')->info('WhatsApp job started', [
            'message_type' => $type,
            'template' => $this->message['template'] ?? null,
            'context' => $context,
            'has_phone' => ! empty($this->message['phone']),
        ]);

        if ($type === 'template') {
            if (! empty($this->message['components'])) {
                $whatsApp->sendTemplateWithComponents(
                    (string) $this->message['phone'],
                    (string) $this->message['template'],
                    $this->message['components'],
                    $this->message['language'] ?? 'es_MX',
                    $context
                );
            } else {
                $whatsApp->sendTemplate(
                    (string) $this->message['phone'],
                    (string) $this->message['template'],
                    $this->message['parameters'] ?? [],
                    $this->message['language'] ?? 'es_MX',
                    $context
                );
            }

            Log::channel('whatsapp')->info('WhatsApp template job finished', [
                'template' => $this->message['template'] ?? null,
                'context' => $context,
            ]);

            return;
        }

        if ($type === 'interactive_buttons') {
            $whatsApp->sendInteractiveButtons(
                (string) $this->message['phone'],
                (string) $this->message['body'],
                $this->message['buttons'] ?? [],
                $context,
                $this->message['header'] ?? null,
                $this->message['footer'] ?? null
            );

            Log::channel('whatsapp')->info('WhatsApp interactive buttons job finished', [
                'context' => $context,
            ]);

            return;
        }

        $whatsApp->sendText(
            (string) $this->message['phone'],
            (string) $this->message['message'],
            $context
        );

        Log::channel('whatsapp')->info('WhatsApp text job finished', [
            'context' => $context,
        ]);
    }
}
