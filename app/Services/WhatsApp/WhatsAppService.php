<?php

namespace App\Services\WhatsApp;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\WhatsAppMessage;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    public function sendText(string $phone, string $message, array $context = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->toMetaPhoneNumber($phone),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        return $this->send($payload, 'text', null, $context);
    }

    public function sendTemplate(
        string $phone,
        string $template,
        array $parameters = [],
        string $language = 'es_MX',
        array $context = []
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->toMetaPhoneNumber($phone),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => [
                    'code' => $language,
                ],
            ],
        ];

        if ($parameters !== []) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn ($parameter): array => [
                            'type' => 'text',
                            'text' => (string) $parameter,
                        ],
                        array_values($parameters)
                    ),
                ],
            ];
        }

        return $this->send($payload, 'template', $template, $context);
    }

    public function sendAppointmentReminder(Appointment $appointment): array
    {
        $appointment->loadMissing(['patient', 'user']);
        $patient = $appointment->patient()->first();
        $professional = $appointment->user()->first();

        return $this->sendTemplate(
            (string) $patient?->phone,
            'appointment_reminder',
            [
                $patient?->name ?: 'paciente',
                optional($appointment->start)->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $professional?->name ?: 'tu profesional',
            ],
            'es_MX',
            [
                'patient_id' => $patient?->id,
                'user_id' => $professional?->id,
                'appointment_id' => $appointment->id,
            ]
        );
    }

    public function sendAppointmentCreated(Appointment $appointment): array
    {
        $appointment->loadMissing(['patient', 'user']);
        $patient = $appointment->patient()->first();
        $professional = $appointment->user()->first();

        return $this->sendTemplate(
            (string) $patient?->phone,
            'appointment_created',
            [
                $patient?->name ?: 'paciente',
                optional($appointment->start)->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $professional?->name ?: 'tu profesional',
            ],
            'es_MX',
            [
                'patient_id' => $patient?->id,
                'user_id' => $professional?->id,
                'appointment_id' => $appointment->id,
            ]
        );
    }

    public function sendPatientInvitation(Patient $patient): array
    {
        return $this->sendTemplate(
            (string) $patient->phone,
            'patient_invitation',
            [
                $patient->name,
                rtrim(config('app.perfil_paciente_url'), '/').'/dashboard',
            ],
            'es_MX',
            [
                'patient_id' => $patient->id,
            ]
        );
    }

    protected function send(array $payload, string $messageType, ?string $template, array $context = []): array
    {
        $audit = WhatsAppMessage::create([
            'user_id' => $context['user_id'] ?? null,
            'patient_id' => $context['patient_id'] ?? null,
            'phone' => $payload['to'],
            'template' => $template,
            'message_type' => $messageType,
            'status' => 'queued',
            'payload' => $payload,
            'response' => null,
        ]);

        try {
            $this->validateConfiguration();

            Log::channel('whatsapp')->debug('WhatsApp request', [
                'whatsapp_message_id' => $audit->id,
                'payload' => $payload,
            ]);

            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($this->messagesEndpoint(), $payload);

            return $this->handleResponse($audit, $response);
        } catch (Throwable $exception) {
            $audit->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'response' => [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
            ]);

            Log::channel('whatsapp')->error('WhatsApp exception', [
                'whatsapp_message_id' => $audit->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'whatsapp_message_id' => $audit->id,
                'meta_message_id' => null,
                'response' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function handleResponse(WhatsAppMessage $audit, Response $response): array
    {
        $body = $response->json() ?? ['raw' => $response->body()];
        $metaMessageId = data_get($body, 'messages.0.id');
        $error = data_get($body, 'error.message');
        $status = $response->successful() ? 'sent' : 'failed';

        $audit->update([
            'meta_message_id' => $metaMessageId,
            'status' => $status,
            'response' => $body,
            'error_message' => $error,
            'sent_at' => $response->successful() ? now() : null,
        ]);

        Log::channel('whatsapp')->debug('WhatsApp response', [
            'whatsapp_message_id' => $audit->id,
            'http_status' => $response->status(),
            'response' => $body,
        ]);

        return [
            'success' => $response->successful(),
            'status' => $status,
            'whatsapp_message_id' => $audit->id,
            'meta_message_id' => $metaMessageId,
            'response' => $body,
            'error' => $error,
        ];
    }

    protected function messagesEndpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version', 'v25.0'),
            config('services.whatsapp.phone_number_id')
        );
    }

    protected function toMetaPhoneNumber(string $phone): string
    {
        return ltrim(PhoneNormalizer::toE164($phone), '+');
    }

    protected function validateConfiguration(): void
    {
        if (! config('services.whatsapp.token') || ! config('services.whatsapp.phone_number_id')) {
            throw new \RuntimeException('WhatsApp Cloud API no esta configurado.');
        }
    }
}
