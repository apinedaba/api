<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * FakeStripeService - Simulador de Stripe para Testing
 * 
 * Las sesiones se guardan en caché de Laravel para que persistan entre requests
 */
class FakeStripeService
{
    const SESSIONS_CACHE_KEY = 'fake_stripe_sessions';
    const WEBHOOKS_LOG_CACHE_KEY = 'fake_stripe_webhooks_log';
    const CACHE_TTL = 3600; // 1 hora

    public static function createCheckoutSession(array $params): object
    {
        $sessionId = 'cs_test_' . Str::random(24);

        $session = [
            'id' => $sessionId,
            'object' => 'checkout.session',
            'status' => 'open',
            'created' => time(),
            'customer' => $params['customer'] ?? null,
            'payment_status' => 'unpaid',
            'metadata' => $params['metadata'] ?? [],
            'line_items' => $params['line_items'] ?? [],
            'success_url' => $params['success_url'] ?? null,
            'cancel_url' => $params['cancel_url'] ?? null,
            'amount_total' => self::calculateTotal($params['line_items'] ?? []),
            'currency' => 'mxn',
            'mode' => 'payment',
        ];

        $sessions = Cache::get(self::SESSIONS_CACHE_KEY, []);
        $sessions[$sessionId] = $session;
        Cache::put(self::SESSIONS_CACHE_KEY, $sessions, self::CACHE_TTL);

        \Log::info('FakeStripe: Checkout session creada', ['session_id' => $sessionId]);

        return (object) [
            'id' => $sessionId,
            'url' => self::getCheckoutUrl($sessionId),
        ];
    }

    public static function getSession(string $sessionId): ?array
    {
        $sessions = Cache::get(self::SESSIONS_CACHE_KEY, []);
        return $sessions[$sessionId] ?? null;
    }

    public static function simulateCheckoutCompleted(string $sessionId, ?string $customerId = null): ?array
    {
        $session = self::getSession($sessionId);
        if (!$session) {
            return null;
        }

        $session['status'] = 'complete';
        $session['payment_status'] = 'paid';
        if ($customerId) {
            $session['customer'] = $customerId;
        }

        $sessions = Cache::get(self::SESSIONS_CACHE_KEY, []);
        $sessions[$sessionId] = $session;
        Cache::put(self::SESSIONS_CACHE_KEY, $sessions, self::CACHE_TTL);

        $webhookPayload = [
            'id' => 'evt_' . Str::random(24),
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'created' => time(),
            'data' => ['object' => $session],
        ];

        self::logWebhook($webhookPayload);
        return $webhookPayload;
    }

    public static function createCustomer(array $params): object
    {
        $customerId = 'cus_test_' . Str::random(14);
        return (object) ['id' => $customerId, 'object' => 'customer', 'email' => $params['email'] ?? null];
    }

    public static function logWebhook(array $webhook): void
    {
        $log = Cache::get(self::WEBHOOKS_LOG_CACHE_KEY, []);
        $log[] = ['timestamp' => now(), 'webhook' => $webhook];
        Cache::put(self::WEBHOOKS_LOG_CACHE_KEY, $log, self::CACHE_TTL);
    }

    public static function getWebhookLog(): array
    {
        return Cache::get(self::WEBHOOKS_LOG_CACHE_KEY, []);
    }

    public static function getAllSessions(): array
    {
        return Cache::get(self::SESSIONS_CACHE_KEY, []);
    }

    public static function reset(): void
    {
        Cache::forget(self::SESSIONS_CACHE_KEY);
        Cache::forget(self::WEBHOOKS_LOG_CACHE_KEY);
    }

    private static function calculateTotal(array $lineItems): int
    {
        $total = 0;
        foreach ($lineItems as $item) {
            if (isset($item['price_data']['unit_amount'], $item['quantity'])) {
                $total += $item['price_data']['unit_amount'] * $item['quantity'];
            }
        }
        return $total;
    }

    private static function getCheckoutUrl(string $sessionId): string
    {
        return config('app.url') . "/fake-stripe/checkout/{$sessionId}";
    }

    public static function getStats(): array
    {
        $all = self::getAllSessions();
        $paid = array_filter($all, fn($s) => $s['payment_status'] === 'paid');
        $unpaid = array_filter($all, fn($s) => $s['payment_status'] === 'unpaid');
        return [
            'total_sessions' => count($all),
            'paid' => count($paid),
            'unpaid' => count($unpaid),
            'webhooks_sent' => count(self::getWebhookLog()),
        ];
    }
}
