<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

            return;
        }

        $whatsApp->sendText(
            (string) $this->message['phone'],
            (string) $this->message['message'],
            $context
        );
    }
}
