<?php

/**
 * Testing Routes - MindBoost Marketing
 * 
 * Rutas SOLO para development/testing para simular Stripe sin depender de él
 * 
 * ⚠️ IMPORTANTE: Estas rutas solo funcionan en APP_DEBUG=true
 * En producción están deshabilitadas completamente
 * 
 * Uso:
 *   POST /api/testing/marketing/webhook/simulate
 *   {
 *       "session_id": "cs_test_xxx",
 *       "customer_id": "cus_test_xxx" (opcional)
 *   }
 * 
 * Respuesta:
 *   {
 *       "success": true,
 *       "campaign_id": 1,
 *       "status": "paid",
 *       "message": "Webhook procesado correctamente"
 *   }
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FakeStripeService;
use App\Services\MarketingPaymentService;
use App\Models\CampaignRequest;

class MarketingTestController extends Controller
{
    /**
     * Simular webhook de Stripe sin necesidad de pago real
     * 
     * Solo funciona en development (APP_DEBUG=true)
     */
    public function simulateWebhook(Request $request): JsonResponse
    {
        // ⚠️ SEGURIDAD: Solo en desarrollo
        if (!config('app.debug')) {
            return response()->json([
                'error' => 'Endpoint solo disponible en development',
            ], 403);
        }

        $request->validate([
            'session_id' => 'required|string',
            'customer_id' => 'nullable|string',
        ]);

        $sessionId = $request->input('session_id');
        $customerId = $request->input('customer_id');

        try {
            // Simular webhook de Stripe
            $webhookData = FakeStripeService::simulateCheckoutCompleted($sessionId, $customerId);

            if (!$webhookData) {
                return response()->json([
                    'error' => 'Sesión no encontrada',
                    'session_id' => $sessionId,
                ], 404);
            }

            // Procesar el webhook como lo haría Stripe
            $sessionObject = (object) $webhookData['data']['object'];
            $service = app(MarketingPaymentService::class);
            $service->handleCheckoutCompleted($sessionObject);

            // Obtener la campaña para devolver info
            $campaignId = $sessionObject->metadata['campaign_request_id'] ?? null;
            $campaign = $campaignId ? CampaignRequest::find($campaignId) : null;

            return response()->json([
                'success' => true,
                'message' => 'Webhook simulado procesado correctamente',
                'session_id' => $sessionId,
                'campaign_id' => $campaignId,
                'campaign_status' => $campaign?->status->value,
                'webhook_event' => $webhookData['type'],
            ]);
        } catch (\Exception $e) {
            \Log::error('MarketingTestController::simulateWebhook error', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'error' => 'Error procesando webhook',
                'message' => $e->getMessage(),
                'session_id' => $sessionId,
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de FakeStripe (para debugging)
     * Solo en desarrollo
     */
    public function getFakeStripeStats(): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'stats' => FakeStripeService::getStats(),
            'sessions' => FakeStripeService::getAllSessions(),
            'webhook_log' => collect(FakeStripeService::getWebhookLog())
                ->take(10) // Últimos 10 webhooks
                ->toArray(),
        ]);
    }

    /**
     * Resetear estado de FakeStripe (para entre tests)
     * Solo en desarrollo
     */
    public function resetFakeStripe(): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        FakeStripeService::reset();

        return response()->json([
            'success' => true,
            'message' => 'FakeStripe state reset',
        ]);
    }

    /**
     * Crear checkout fake (para testing sin UI)
     * Simula lo que haría createMarketingCheckout
     */
    public function createFakeCheckout(Request $request): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'campaign_request_id' => 'required|integer|exists:campaign_requests,id',
        ]);

        try {
            $campaignRequestId = $request->input('campaign_request_id');
            $campaign = CampaignRequest::findOrFail($campaignRequestId);

            // Crear sesión fake
            $session = FakeStripeService::createCheckoutSession([
                'customer' => 'cus_test_' . $campaign->user_id,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'mxn',
                        'product_data' => [
                            'name' => $campaign->marketingPackage->name,
                        ],
                        'unit_amount' => $campaign->marketingPackage->price * 100,
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'campaign_request_id' => $campaignRequestId,
                    'type' => 'marketing',
                    'user_id' => $campaign->user_id,
                    'marketing_package_id' => $campaign->marketing_package_id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'url' => $session->url,
                'message' => 'Sesión fake creada. Usa /testing/marketing/webhook/simulate para completar el pago',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
