<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Server-to-server client. The browser never receives this token. */
class AdminVendedoresClient
{
    private function request(): PendingRequest
    {
        $token = (string) config('services.admin_vendedores.integration_token');
        if ($token === '') {
            throw new RuntimeException('La integración con Admin Vendedores no está configurada.');
        }

        return Http::baseUrl(rtrim((string) config('services.admin_vendedores.base_url'), '/'))
            ->acceptJson()->asJson()
            ->withHeaders(['X-Mindmeet-Integration-Token' => $token])
            ->connectTimeout((int) config('services.admin_vendedores.connect_timeout', 3))
            ->timeout((int) config('services.admin_vendedores.timeout', 10));
    }

    public function vendors(): array { return $this->send(fn () => $this->request()->get('/internal/v1/vendors')); }
    public function vendor(int|string $id): array { return $this->send(fn () => $this->request()->get("/internal/v1/vendors/{$id}")); }
    public function vendorByQr(string $token): array { return $this->send(fn () => $this->request()->get('/internal/v1/vendors/qr/' . rawurlencode($token))); }
    public function vendorPsychologists(int|string $id): array { return $this->send(fn () => $this->request()->get("/internal/v1/vendors/{$id}/psychologists")); }
    public function registerReferral(array $data): array { return $this->send(fn () => $this->request()->post('/internal/v1/vendor-referrals', $data)); }
    public function syncRecovery(array $data): array { return $this->send(fn () => $this->request()->post('/internal/v1/recoveries/sync', $data)); }
    public function excludeRecovery(int $mindmeetUserId): array { return $this->send(fn () => $this->request()->post("/internal/v1/recoveries/{$mindmeetUserId}/exclude")); }
    public function confirmRecoveryPayment(int $mindmeetUserId): array { return $this->send(fn () => $this->request()->post("/internal/v1/recoveries/{$mindmeetUserId}/payment")); }
    public function commissionRules(): array { return $this->send(fn () => $this->request()->get('/internal/v1/commission-rules')); }
    public function createCommissionRule(array $data): array { return $this->send(fn () => $this->request()->post('/internal/v1/commission-rules', $data)); }
    public function deactivateCommissionRule(int|string $id): array { return $this->send(fn () => $this->request()->patch("/internal/v1/commission-rules/{$id}/deactivate")); }
    public function recoveryCommissions(): array { return $this->send(fn () => $this->request()->get('/internal/v1/recovery-commissions')); }
    public function markRecoveryCommissionsPaid(array $ids): array { return $this->send(fn () => $this->request()->patch('/internal/v1/recovery-commissions/mark-paid', ['ids' => array_values($ids)])); }
    public function createVendor(array $data): array { return $this->send(fn () => $this->request()->post('/internal/v1/vendors', $data)); }
    public function updateVendor(int|string $id, array $data): array { return $this->send(fn () => $this->request()->put("/internal/v1/vendors/{$id}", $data)); }
    public function deactivateVendor(int|string $id): array { return $this->send(fn () => $this->request()->delete("/internal/v1/vendors/{$id}")); }

    private function send(callable $request): array
    {
        try {
            $response = $request();
        } catch (\Throwable $exception) {
            throw new RuntimeException('No fue posible conectar con Admin Vendedores.', previous: $exception);
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $detail = data_get($response->json(), 'detail');
        $message = is_array($detail)
            ? collect($detail)->pluck('msg')->filter()->implode(' ')
            : $detail;
        $message = $message
            ?: data_get($response->json(), 'message')
            ?: 'Admin Vendedores no pudo procesar la solicitud.';
        throw new RuntimeException((string) $message, $response->status());
    }
}
