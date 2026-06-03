<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppService
{
    public function sendToUser(User $user, string $message): bool
    {
        $phone = $this->resolveProfessionalPhone($user);

        if (!$phone) {
            return false;
        }

        return $this->send($phone, $message);
    }

    public function send(string $phone, string $message): bool
    {
        if (!config('services.twilio.enabled')) {
            return false;
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = $this->formatWhatsAppAddress(config('services.twilio.whatsapp_from'));
        $to = $this->formatWhatsAppAddress($phone);

        if (!$sid || !$token || !$from || !$to) {
            Log::warning('Twilio WhatsApp skipped: missing configuration or phone');
            return false;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->connectTimeout(2)
                ->timeout(5)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $message,
                ]);

            if (!$response->successful()) {
                Log::warning('Twilio WhatsApp failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $th) {
            Log::warning('Twilio WhatsApp exception', [
                'message' => $th->getMessage(),
            ]);
            return false;
        }
    }

    protected function resolveProfessionalPhone(User $user): ?string
    {
        return data_get($user->contacto, 'whatsapp')
            ?: data_get($user->contacto, 'telefono')
            ?: data_get($user->contacto, 'phone');
    }

    protected function formatWhatsAppAddress(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = trim($phone);

        if (str_starts_with($phone, 'whatsapp:')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            $digits = '52' . $digits;
        }

        if ($digits === '') {
            return null;
        }

        return 'whatsapp:+' . $digits;
    }
}
