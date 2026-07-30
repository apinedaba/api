<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): JsonResponse|Response
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && hash_equals((string) config('services.whatsapp.verify_token'), (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::channel('whatsapp')->warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_present' => filled($token),
        ]);

        return response()->json(['message' => 'Forbidden'], 403);
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::channel('whatsapp')->debug('WhatsApp webhook received', [
            'payload' => $payload,
        ]);

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $field = data_get($change, 'field');
                $value = data_get($change, 'value', []);

                match ($field) {
                    'messages' => $this->handleMessagesChange($value),
                    'message_status' => $this->handleMessagesChange($value),
                    'message_template_status_update' => $this->handleTemplateStatusUpdate($value),
                    default => Log::channel('whatsapp')->debug('WhatsApp webhook field ignored', [
                        'field' => $field,
                        'value' => $value,
                    ]),
                };
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleMessagesChange(array $value): void
    {
        foreach (data_get($value, 'statuses', []) as $status) {
            $this->updateMessageStatus($status);
        }

        foreach (data_get($value, 'messages', []) as $message) {
            $buttonPayload = data_get($message, 'button.payload')
                ?: data_get($message, 'interactive.button_reply.id');

            WhatsAppMessage::create([
                'phone' => (string) data_get($message, 'from'),
                'template' => null,
                'message_type' => $buttonPayload ? 'button_reply' : 'incoming',
                'meta_message_id' => data_get($message, 'id'),
                'status' => 'received',
                'payload' => $message,
                'response' => $value,
                'sent_at' => null,
            ]);

            if ($buttonPayload) {
                $this->handleButtonPayload((string) $buttonPayload, $message);
            }
        }
    }

    protected function handleButtonPayload(string $payload, array $message): void
    {
        if (! preg_match('/^appointment:(\d+):(confirm|postpone|cancel)$/', $payload, $matches)) {
            Log::channel('whatsapp')->debug('WhatsApp button payload ignored', [
                'payload' => $payload,
                'message_id' => data_get($message, 'id'),
            ]);

            return;
        }

        $appointment = Appointment::find((int) $matches[1]);

        if (! $appointment) {
            Log::channel('whatsapp')->warning('WhatsApp button appointment not found', [
                'payload' => $payload,
                'message_id' => data_get($message, 'id'),
            ]);

            return;
        }

        match ($matches[2]) {
            'confirm' => $appointment->forceFill([
                'statusPatient' => 'Confirmed',
                'state' => 'Confirmada',
            ])->save(),
            'postpone' => $appointment->forceFill([
                'statusPatient' => 'Reschedule Requested',
                'state' => 'Reprogramacion solicitada',
            ])->save(),
            'cancel' => $appointment->forceFill([
                'statusPatient' => 'Cancel',
                'state' => 'Cancelada',
            ])->save(),
        };

        Log::channel('whatsapp')->info('WhatsApp appointment action applied', [
            'appointment_id' => $appointment->id,
            'action' => $matches[2],
            'message_id' => data_get($message, 'id'),
        ]);
    }

    protected function updateMessageStatus(array $status): void
    {
        $metaMessageId = data_get($status, 'id');
        $statusValue = data_get($status, 'status');

        if (! $metaMessageId || ! $statusValue) {
            return;
        }

        $allowedStatuses = ['sent', 'delivered', 'read', 'failed'];

        if (! in_array($statusValue, $allowedStatuses, true)) {
            return;
        }

        WhatsAppMessage::where('meta_message_id', $metaMessageId)->update([
            'status' => $statusValue,
            'response' => $status,
            'error_message' => data_get($status, 'errors.0.title') ?: data_get($status, 'errors.0.message'),
        ]);
    }

    protected function handleTemplateStatusUpdate(array $value): void
    {
        Log::channel('whatsapp')->debug('WhatsApp template status update', [
            'payload' => $value,
        ]);
    }
}
