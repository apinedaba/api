<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $uniqueFor = 600;

    public function __construct(protected array $message)
    {
    }

    public function uniqueId(): string
    {
        return hash('sha256', json_encode($this->message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
                $result = $whatsApp->sendTemplateWithComponents(
                    (string) $this->message['phone'],
                    (string) $this->message['template'],
                    $this->message['components'],
                    $this->message['language'] ?? 'es_MX',
                    $context
                );
            } else {
                $result = $whatsApp->sendTemplate(
                    (string) $this->message['phone'],
                    (string) $this->message['template'],
                    $this->message['parameters'] ?? [],
                    $this->message['language'] ?? 'es_MX',
                    $context
                );
            }

            $this->ensureSuccessful($result ?? []);
            Log::channel('whatsapp')->info('WhatsApp template job finished', [
                'template' => $this->message['template'] ?? null,
                'context' => $context,
            ]);

            return;
        }

        if ($type === 'interactive_buttons') {
            $result = $whatsApp->sendInteractiveButtons(
                (string) $this->message['phone'],
                (string) $this->message['body'],
                $this->message['buttons'] ?? [],
                $context,
                $this->message['header'] ?? null,
                $this->message['footer'] ?? null
            );

            $this->ensureSuccessful($result ?? []);
            Log::channel('whatsapp')->info('WhatsApp interactive buttons job finished', [
                'context' => $context,
            ]);

            return;
        }

        $result = $whatsApp->sendText(
            (string) $this->message['phone'],
            (string) $this->message['message'],
            $context
        );

        $this->ensureSuccessful($result ?? []);
        Log::channel('whatsapp')->info('WhatsApp text job finished', [
            'context' => $context,
        ]);
    }

    private function ensureSuccessful(array $result): void
    {
        if (($result['success'] ?? false) === true) return;
        throw new \RuntimeException((string) ($result['error'] ?? 'Meta WhatsApp Cloud API rechazó el envío.'));
    }
}
