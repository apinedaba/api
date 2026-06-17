<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\ProfessionalWithdrawal;
use App\Models\StripeTransactionLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ProfessionalPayoutController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret_key') ?? env('STRIPE_SECRET_KEY'));
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->refreshConnectAccountFromStripe($user);

        return response()->json([
            'connect' => $this->connectSummary($user->fresh()),
            'balance' => $this->balanceSummary($user),
            'withdrawals' => ProfessionalWithdrawal::where('user_id', $user->id)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function onboardingLink(Request $request): JsonResponse
    {
        $request->validate([
            'return_url' => ['nullable', 'url'],
            'refresh_url' => ['nullable', 'url'],
        ]);

        $user = $request->user();
        $account = $this->ensureConnectAccount($user);
        $frontendUrl = $this->resolvePsychologistFrontendUrl();

        $accountLink = $this->stripe->accountLinks->create([
            'account' => $account->id,
            'refresh_url' => $request->input('refresh_url') ?: "{$frontendUrl}/perfil/pagos?connect=refresh",
            'return_url' => $request->input('return_url') ?: "{$frontendUrl}/perfil/pagos?connect=return",
            'type' => 'account_onboarding',
        ]);

        $this->logStripeTransaction([
            'user_id' => $user->id,
            'event_type' => 'connect.account_link.created',
            'direction' => 'connect_onboarding',
            'stripe_object_type' => 'account_link',
            'stripe_object_id' => $accountLink->url,
            'status' => 'created',
            'payload' => [
                'account' => $account->id,
                'expires_at' => $accountLink->expires_at ?? null,
            ],
        ]);

        return response()->json(['url' => $accountLink->url]);
    }

    public function refreshStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->refreshConnectAccountFromStripe($user);

        return response()->json(['connect' => $this->connectSummary($user->fresh())]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'auto_payout' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $this->refreshConnectAccountFromStripe($user);
        $user->refresh();

        if (!$user->stripe_connect_account_id || !$user->stripe_connect_payouts_enabled) {
            return response()->json([
                'message' => 'Completa la configuracion de Stripe Connect antes de retirar.',
                'connect' => $this->connectSummary($user),
            ], 422);
        }

        $amount = round((float) $request->input('amount'), 2);
        $balance = $this->balanceSummary($user);

        if ($amount > $balance['available']) {
            return response()->json([
                'message' => 'El monto solicitado excede tu saldo disponible.',
                'balance' => $balance,
            ], 422);
        }

        $withdrawal = DB::transaction(function () use ($user, $amount) {
            $allocation = $this->allocatePaymentsForAmount($user, $amount);

            if (round($allocation['total'], 2) < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo disponible insuficiente.',
                ]);
            }

            $withdrawal = ProfessionalWithdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => 'MXN',
                'status' => ProfessionalWithdrawal::STATUS_REQUESTED,
                'stripe_connect_account_id' => $user->stripe_connect_account_id,
                'requested_at' => now(),
                'metadata' => [
                    'payment_count' => count($allocation['items']),
                ],
            ]);

            foreach ($allocation['items'] as $item) {
                $withdrawal->payments()->attach($item['payment']->id, ['amount' => $item['amount']]);
            }

            return $withdrawal;
        });

        try {
            $transfer = $this->stripe->transfers->create([
                'amount' => $this->toStripeAmount($withdrawal->amount),
                'currency' => 'mxn',
                'destination' => $withdrawal->stripe_connect_account_id,
                'metadata' => [
                    'withdrawal_id' => (string) $withdrawal->id,
                    'user_id' => (string) $user->id,
                    'source' => 'mindmeet_professional_withdrawal',
                ],
            ], [
                'idempotency_key' => "withdrawal_transfer_{$withdrawal->id}",
            ]);

            $withdrawal->update([
                'status' => ProfessionalWithdrawal::STATUS_TRANSFERRED,
                'stripe_transfer_id' => $transfer->id,
                'transferred_at' => now(),
            ]);

            $withdrawal->payments()->update(['payout_status' => 'transferred']);

            $this->logStripeTransaction([
                'user_id' => $user->id,
                'professional_withdrawal_id' => $withdrawal->id,
                'event_type' => 'transfer.created',
                'direction' => 'platform_to_connected_account',
                'stripe_object_type' => 'transfer',
                'stripe_object_id' => $transfer->id,
                'status' => $transfer->reversed ? 'reversed' : 'created',
                'amount' => $withdrawal->amount,
                'currency' => 'MXN',
                'payload' => $this->stripePayload($transfer),
            ]);

            if ($request->boolean('auto_payout', true)) {
                $this->createConnectedAccountPayout($withdrawal, $user);
            }
        } catch (\Throwable $exception) {
            $withdrawal->update([
                'status' => ProfessionalWithdrawal::STATUS_FAILED,
                'failed_at' => now(),
                'failure_code' => $exception instanceof ApiErrorException ? $exception->getStripeCode() : null,
                'failure_message' => $exception->getMessage(),
            ]);

            $this->logStripeTransaction([
                'user_id' => $user->id,
                'professional_withdrawal_id' => $withdrawal->id,
                'event_type' => 'withdrawal.failed',
                'direction' => 'platform_to_connected_account',
                'status' => 'failed',
                'amount' => $withdrawal->amount,
                'currency' => 'MXN',
                'error' => [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'stripe_code' => $exception instanceof ApiErrorException ? $exception->getStripeCode() : null,
                ],
            ]);

            Log::warning('Professional withdrawal failed', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo procesar el retiro en Stripe.',
                'reason' => $exception->getMessage(),
                'withdrawal' => $withdrawal->fresh(),
            ], 422);
        }

        return response()->json([
            'message' => 'Retiro solicitado correctamente.',
            'withdrawal' => $withdrawal->fresh('payments'),
            'balance' => $this->balanceSummary($user),
        ], 201);
    }

    public function handleStripeConnectEvent(object $event): void
    {
        $object = $event->data->object ?? null;
        $objectId = $object->id ?? null;

        if (!$object || !$objectId) {
            return;
        }

        if (str_starts_with($event->type, 'account.')) {
            $accountId = $object->id ?? null;
            $user = $accountId ? User::where('stripe_connect_account_id', $accountId)->first() : null;

            if ($user) {
                $this->syncUserConnectFields($user, $object);
                $this->logStripeTransaction([
                    'user_id' => $user->id,
                    'event_type' => $event->type,
                    'direction' => 'stripe_webhook',
                    'stripe_object_type' => 'account',
                    'stripe_object_id' => $accountId,
                    'status' => $object->payouts_enabled ? 'payouts_enabled' : 'pending',
                    'payload' => $this->stripePayload($object),
                ]);
            }

            return;
        }

        $withdrawal = ProfessionalWithdrawal::query()
            ->where('stripe_transfer_id', $objectId)
            ->orWhere('stripe_payout_id', $objectId)
            ->first();

        if (!$withdrawal) {
            return;
        }

        $status = $object->status ?? null;
        $updates = [];

        if ($event->type === 'payout.paid') {
            $updates = [
                'status' => ProfessionalWithdrawal::STATUS_PAID,
                'paid_at' => now(),
            ];
            $withdrawal->payments()->update(['payout_status' => 'paid']);
        } elseif (in_array($event->type, ['payout.failed', 'transfer.failed'], true)) {
            $updates = [
                'status' => ProfessionalWithdrawal::STATUS_FAILED,
                'failed_at' => now(),
                'failure_code' => $object->failure_code ?? null,
                'failure_message' => $object->failure_message ?? null,
            ];
        }

        if (!empty($updates)) {
            $withdrawal->update($updates);
        }

        $this->logStripeTransaction([
            'user_id' => $withdrawal->user_id,
            'professional_withdrawal_id' => $withdrawal->id,
            'event_type' => $event->type,
            'direction' => 'stripe_webhook',
            'stripe_object_type' => $object->object ?? null,
            'stripe_object_id' => $objectId,
            'status' => $status,
            'amount' => isset($object->amount) ? ((float) $object->amount / 100) : null,
            'currency' => isset($object->currency) ? strtoupper($object->currency) : null,
            'payload' => $this->stripePayload($object),
        ]);
    }

    private function ensureConnectAccount(User $user): object
    {
        if ($user->stripe_connect_account_id) {
            $account = $this->stripe->accounts->retrieve($user->stripe_connect_account_id);
            $this->syncUserConnectFields($user, $account);

            return $account;
        }

        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => 'MX',
            'email' => $user->email,
            'business_type' => 'individual',
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
                'source' => 'mindmeet',
            ],
        ]);

        $this->syncUserConnectFields($user, $account);
        $this->logStripeTransaction([
            'user_id' => $user->id,
            'event_type' => 'account.created',
            'direction' => 'connect_onboarding',
            'stripe_object_type' => 'account',
            'stripe_object_id' => $account->id,
            'status' => 'created',
            'payload' => $this->stripePayload($account),
        ]);

        return $account;
    }

    private function refreshConnectAccountFromStripe(User $user): void
    {
        if (!$user->stripe_connect_account_id) {
            return;
        }

        try {
            $account = $this->stripe->accounts->retrieve($user->stripe_connect_account_id);
            $this->syncUserConnectFields($user, $account);
        } catch (\Throwable $exception) {
            Log::warning('Unable to refresh Stripe Connect account', [
                'user_id' => $user->id,
                'stripe_connect_account_id' => $user->stripe_connect_account_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function syncUserConnectFields(User $user, object $account): void
    {
        $chargesEnabled = (bool) ($account->charges_enabled ?? false);
        $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);

        $user->forceFill([
            'stripe_connect_account_id' => $account->id,
            'stripe_connect_charges_enabled' => $chargesEnabled,
            'stripe_connect_payouts_enabled' => $payoutsEnabled,
            'stripe_connect_onboarding_completed_at' => $payoutsEnabled
                ? ($user->stripe_connect_onboarding_completed_at ?: now())
                : null,
        ])->save();
    }

    private function connectSummary(User $user): array
    {
        return [
            'account_id' => $user->stripe_connect_account_id,
            'charges_enabled' => (bool) $user->stripe_connect_charges_enabled,
            'payouts_enabled' => (bool) $user->stripe_connect_payouts_enabled,
            'onboarding_completed' => filled($user->stripe_connect_onboarding_completed_at),
            'onboarding_completed_at' => $user->stripe_connect_onboarding_completed_at?->toIso8601String(),
        ];
    }

    private function balanceSummary(User $user): array
    {
        $available = 0.0;
        $gross = 0.0;
        $mindmeetFees = 0.0;
        $pending = 0.0;
        $paid = 0.0;

        foreach ($this->withdrawablePayments($user) as $payment) {
            $gross += (float) $payment->amount;
            $netAmount = $this->netPsychologistAmount($payment);
            $mindmeetFees += max((float) $payment->amount - $netAmount, 0);
            $available += $this->availablePaymentAmount($payment);
        }

        $pending = ProfessionalWithdrawal::where('user_id', $user->id)
            ->whereIn('status', [
                ProfessionalWithdrawal::STATUS_REQUESTED,
                ProfessionalWithdrawal::STATUS_TRANSFERRED,
                ProfessionalWithdrawal::STATUS_PAYOUT_CREATED,
            ])
            ->sum('amount');

        $paid = ProfessionalWithdrawal::where('user_id', $user->id)
            ->where('status', ProfessionalWithdrawal::STATUS_PAID)
            ->sum('amount');

        return [
            'available' => round($available, 2),
            'gross_unwithdrawn' => round($gross, 2),
            'mindmeet_fee_unwithdrawn' => round($mindmeetFees, 2),
            'pending' => round((float) $pending, 2),
            'paid' => round((float) $paid, 2),
            'currency' => 'MXN',
            'platform_fee_rate' => (float) config('services.checkout.platform_fee_rate', 0.06),
        ];
    }

    private function withdrawablePayments(User $user)
    {
        return Payment::query()
            ->with(['withdrawals' => fn ($query) => $query->where('professional_withdrawals.status', '!=', ProfessionalWithdrawal::STATUS_FAILED)])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('amount', '>', 0)
            ->oldest()
            ->get()
            ->filter(fn (Payment $payment) => $this->isMindMeetCollectedPayment($payment))
            ->filter(fn (Payment $payment) => $this->availablePaymentAmount($payment) > 0.009)
            ->values();
    }

    private function availablePaymentAmount(Payment $payment): float
    {
        $baseAmount = $this->netPsychologistAmount($payment);
        $allocated = $payment->withdrawals->sum(fn ($withdrawal) => (float) $withdrawal->pivot->amount);

        return round(max($baseAmount - $allocated, 0), 2);
    }

    private function netPsychologistAmount(Payment $payment): float
    {
        if ($payment->psychologist_amount !== null) {
            return round((float) $payment->psychologist_amount, 2);
        }

        if ($payment->platform_fee_amount !== null) {
            return round(max((float) $payment->amount - (float) $payment->platform_fee_amount, 0), 2);
        }

        if ($this->isMindMeetCollectedPayment($payment)) {
            $feeRate = (float) config('services.checkout.platform_fee_rate', 0.06);

            return round(((float) $payment->amount) / (1 + $feeRate), 2);
        }

        return round((float) $payment->amount, 2);
    }

    private function isMindMeetCollectedPayment(Payment $payment): bool
    {
        return filled($payment->stripe_payment_id)
            && in_array(strtolower((string) $payment->payment_method), ['card', 'oxxo', 'stripe'], true);
    }

    private function allocatePaymentsForAmount(User $user, float $amount): array
    {
        $remaining = round($amount, 2);
        $items = [];

        foreach ($this->withdrawablePayments($user) as $payment) {
            if ($remaining <= 0) {
                break;
            }

            $available = $this->availablePaymentAmount($payment);
            $allocated = round(min($available, $remaining), 2);

            if ($allocated <= 0) {
                continue;
            }

            $items[] = [
                'payment' => $payment,
                'amount' => $allocated,
            ];

            $remaining = round($remaining - $allocated, 2);
        }

        return [
            'items' => $items,
            'total' => round($amount - max($remaining, 0), 2),
        ];
    }

    private function createConnectedAccountPayout(ProfessionalWithdrawal $withdrawal, User $user): void
    {
        try {
            $payout = $this->stripe->payouts->create([
                'amount' => $this->toStripeAmount($withdrawal->amount),
                'currency' => 'mxn',
                'metadata' => [
                    'withdrawal_id' => (string) $withdrawal->id,
                    'user_id' => (string) $user->id,
                    'source' => 'mindmeet_professional_withdrawal',
                ],
            ], [
                'stripe_account' => $withdrawal->stripe_connect_account_id,
                'idempotency_key' => "withdrawal_payout_{$withdrawal->id}",
            ]);

            $withdrawal->update([
                'status' => ProfessionalWithdrawal::STATUS_PAYOUT_CREATED,
                'stripe_payout_id' => $payout->id,
                'payout_created_at' => now(),
            ]);

            $this->logStripeTransaction([
                'user_id' => $user->id,
                'professional_withdrawal_id' => $withdrawal->id,
                'event_type' => 'payout.created',
                'direction' => 'connected_account_to_bank',
                'stripe_object_type' => 'payout',
                'stripe_object_id' => $payout->id,
                'status' => $payout->status ?? 'created',
                'amount' => $withdrawal->amount,
                'currency' => 'MXN',
                'payload' => $this->stripePayload($payout),
            ]);
        } catch (\Throwable $exception) {
            $withdrawal->update([
                'failure_code' => $exception instanceof ApiErrorException ? $exception->getStripeCode() : null,
                'failure_message' => 'Transferencia creada, payout bancario pendiente: ' . $exception->getMessage(),
            ]);

            $this->logStripeTransaction([
                'user_id' => $user->id,
                'professional_withdrawal_id' => $withdrawal->id,
                'event_type' => 'payout.create_failed',
                'direction' => 'connected_account_to_bank',
                'status' => 'failed',
                'amount' => $withdrawal->amount,
                'currency' => 'MXN',
                'error' => [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'stripe_code' => $exception instanceof ApiErrorException ? $exception->getStripeCode() : null,
                ],
            ]);
        }
    }

    private function logStripeTransaction(array $payload): void
    {
        StripeTransactionLog::create($payload);
    }

    private function stripePayload(mixed $object): array
    {
        if (is_object($object) && method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        return json_decode(json_encode($object), true) ?: [];
    }

    private function toStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function resolvePsychologistFrontendUrl(): string
    {
        $candidates = [
            config('app.front_url_psicologo'),
            app()->environment('local') ? 'http://localhost:5173' : null,
            config('app.front_url_user'),
            config('app.front_url'),
            config('app.frontend_url'),
            'https://minder.mindmeet.com.mx',
        ];

        foreach ($candidates as $candidate) {
            $url = trim((string) $candidate);
            if ($url === '') {
                continue;
            }

            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                $url = 'https://' . ltrim($url, '/');
            }

            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return rtrim($url, '/');
            }
        }

        return 'https://minder.mindmeet.com.mx';
    }
}
