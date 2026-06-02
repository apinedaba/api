<?php

namespace App\Services;

use App\Enums\CampaignRequestStatus;
use App\Enums\GroupCampaignStatus;
use App\Mail\CampaignPaymentCompletedMail;
use App\Models\CampaignRequest;
use App\Models\GroupCampaign;
use App\Models\FailedWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MarketingPaymentService
{
    private const MAX_RETRY_ATTEMPTS = 5;
    private const RETRY_DELAY_MINUTES = 5;

    /**
     * Procesa el evento 'checkout.session.completed' de Stripe.
     *
     * Solo actúa si la sesión contiene 'campaign_request_id' en su metadata,
     * lo que identifica un pago del módulo MindBoost.
     *
     * Seguro para llamarse desde múltiples webhooks (idempotente):
     * si la CampaignRequest ya no está en 'pending_payment', el método retorna silenciosamente.
     *
     * Con error handling: si falla, guarda en failed_webhook_events para retry automático.
     *
     * @param  \Stripe\Checkout\Session|object  $session  Objeto de la sesión de Stripe.
     */
    public function handleCheckoutCompleted(object $session): void
    {
        $campaignRequestId = data_get($session, 'metadata.campaign_request_id');

        if (! $campaignRequestId) {
            return;
        }

        try {
            DB::transaction(function () use ($campaignRequestId): void {
                /** @var CampaignRequest|null $campaignRequest */
                $campaignRequest = CampaignRequest::where('id', $campaignRequestId)
                    ->lockForUpdate()
                    ->first();

                if (! $campaignRequest) {
                    Log::warning('MindBoost Webhook: CampaignRequest no encontrada.', [
                        'campaign_request_id' => $campaignRequestId,
                    ]);
                    return;
                }

                // ── Idempotencia ─────────────────────────────────────────────────
                // Si ya fue procesado en un webhook previo, salimos sin hacer nada.
                if ($campaignRequest->status !== CampaignRequestStatus::PendingPayment) {
                    Log::info('MindBoost Webhook: CampaignRequest ya procesada, se omite.', [
                        'campaign_request_id' => $campaignRequestId,
                        'status'             => $campaignRequest->status->value,
                    ]);
                    return;
                }

                // ── 1. Marcar la solicitud como pagada ────────────────────────────
                $campaignRequest->update(['status' => CampaignRequestStatus::Paid->value]);

                Log::info('MindBoost Webhook: CampaignRequest marcada como pagada.', [
                    'campaign_request_id' => $campaignRequestId,
                ]);

                // ── 1.5. Enviar email de confirmación al psicólogo ────────────────
                try {
                    $campaignRequest->load(['user', 'marketingPackage']);
                    Mail::to($campaignRequest->user->email)
                        ->send(new CampaignPaymentCompletedMail($campaignRequest));

                    Log::info('MindBoost Webhook: Email de confirmación enviado.', [
                        'campaign_request_id' => $campaignRequestId,
                        'user_email'          => $campaignRequest->user->email,
                    ]);
                } catch (Throwable $e) {
                    Log::error('MindBoost Webhook: Error enviando email', [
                        'campaign_request_id' => $campaignRequestId,
                        'error'               => $e->getMessage(),
                    ]);
                    // No fallar la transacción por error de email
                }

                // ── 2. Lógica de campaña grupal ───────────────────────────────────
                if (! $campaignRequest->group_campaign_id) {
                    return;
                }

                /** @var GroupCampaign|null $groupCampaign */
                $groupCampaign = GroupCampaign::where('id', $campaignRequest->group_campaign_id)
                    ->lockForUpdate()
                    ->first();

                if (! $groupCampaign) {
                    return;
                }

                // Incrementar el contador de slots PAGADOS.
                $groupCampaign->increment('current_slots');
                $groupCampaign->refresh();

                $maxSlots = $groupCampaign->marketingPackage?->max_slots;

                if ($maxSlots && $groupCampaign->current_slots >= $maxSlots) {
                    $groupCampaign->update(['status' => GroupCampaignStatus::Full->value]);

                    Log::info('MindBoost Webhook: GroupCampaign alcanzó cupo máximo.', [
                        'group_campaign_id' => $groupCampaign->id,
                        'current_slots'     => $groupCampaign->current_slots,
                        'max_slots'         => $maxSlots,
                    ]);
                }
            });
        } catch (Throwable $e) {
            $this->handleWebhookError(
                'checkout.session.completed',
                data_get($session, 'id'),
                (array) $session,
                $e,
                ['campaign_request_id' => $campaignRequestId]
            );
        }
    }

    /**
     * Maneja errores de webhook guardándolos para retry automático.
     */
    private function handleWebhookError(
        string $eventType,
        ?string $stripeId,
        array $payload,
        Throwable $exception,
        array $metadata = []
    ): void {
        Log::error('MindBoost Webhook Error', [
            'event_type' => $eventType,
            'stripe_id' => $stripeId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        try {
            // Buscar si ya existe este webhook fallido
            $existing = FailedWebhookEvent::where('stripe_id', $stripeId)
                ->where('event_type', $eventType)
                ->where('resolved', false)
                ->first();

            if ($existing) {
                // Incrementar intentos y programar nuevo retry
                $nextRetry = Carbon::now()->addMinutes(self::RETRY_DELAY_MINUTES * $existing->attempt_count);

                $existing->update([
                    'attempt_count' => $existing->attempt_count + 1,
                    'next_retry_at' => $nextRetry,
                    'error_message' => $exception->getMessage(),
                    'error_trace' => $exception->getTraceAsString(),
                ]);

                Log::warning('MindBoost Webhook: Reintentando evento.', [
                    'event_type' => $eventType,
                    'stripe_id' => $stripeId,
                    'attempt' => $existing->attempt_count,
                    'next_retry' => $nextRetry,
                ]);

                // Si ya ha fallado 3 veces, notificar al psicólogo
                if ($existing->attempt_count >= 3 && $metadata['campaign_request_id'] ?? null) {
                    $this->notifyPaymentFailed(
                        $metadata['campaign_request_id'],
                        $exception->getMessage()
                    );
                }
            } else {
                // Crear nuevo registro de error
                $nextRetry = Carbon::now()->addMinutes(self::RETRY_DELAY_MINUTES);

                FailedWebhookEvent::create([
                    'event_type' => $eventType,
                    'stripe_id' => $stripeId,
                    'payload' => $payload,
                    'error_message' => $exception->getMessage(),
                    'error_trace' => $exception->getTraceAsString(),
                    'attempt_count' => 1,
                    'next_retry_at' => $nextRetry,
                    'metadata' => $metadata,
                ]);

                Log::warning('MindBoost Webhook: Guardado para retry automático.', [
                    'event_type' => $eventType,
                    'stripe_id' => $stripeId,
                    'next_retry' => $nextRetry,
                ]);
            }
        } catch (Throwable $e) {
            Log::critical('Error guardando failed webhook event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica al psicólogo que el pago de su campaña falló.
     */
    private function notifyPaymentFailed(int $campaignRequestId, string $errorMessage): void
    {
        try {
            $campaignRequest = CampaignRequest::with(['user', 'marketingPackage'])->find($campaignRequestId);

            if (! $campaignRequest) {
                Log::warning('CampaignRequest no encontrada para notificar fallo', [
                    'campaign_request_id' => $campaignRequestId,
                ]);
                return;
            }

            Mail::to($campaignRequest->user->email)
                ->send(new \App\Mail\CampaignPaymentFailedMail($campaignRequest, $errorMessage));

            Log::info('Notificación de fallo de pago enviada', [
                'campaign_request_id' => $campaignRequestId,
                'user_email' => $campaignRequest->user->email,
            ]);
        } catch (Throwable $e) {
            Log::error('Error notificando fallo de pago', [
                'campaign_request_id' => $campaignRequestId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reintenta procesar webhooks fallidos.
     * Debe ejecutarse periódicamente via scheduled task.
     */
    public static function retryFailedWebhooks(): void
    {
        $failedEvents = FailedWebhookEvent::where('resolved', false)
            ->where('attempt_count', '<', self::MAX_RETRY_ATTEMPTS)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', Carbon::now())
            ->orderBy('next_retry_at')
            ->take(10)
            ->get();

        foreach ($failedEvents as $event) {
            try {
                if ($event->event_type === 'checkout.session.completed') {
                    $session = (object) $event->payload;
                    app(self::class)->handleCheckoutCompleted($session);

                    // Marcar como resuelto
                    $event->update([
                        'resolved' => true,
                        'resolved_at' => Carbon::now(),
                    ]);

                    Log::info('MindBoost Webhook: Retry exitoso.', [
                        'event_type' => $event->event_type,
                        'stripe_id' => $event->stripe_id,
                        'attempt' => $event->attempt_count,
                    ]);
                }
            } catch (Throwable $e) {
                // Ya está manejado por handleWebhookError
                app(self::class)->handleCheckoutCompleted((object) $event->payload);
            }
        }
    }
}
