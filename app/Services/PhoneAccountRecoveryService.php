<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use App\Support\PatientIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PhoneAccountRecoveryService
{
    private const EXPIRES_IN_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly BrevoSmsService $sms)
    {
    }

    public function request(string $accountType, string $phone): void
    {
        $phone = PatientIdentity::normalizePhone($phone);
        $account = $phone ? $this->findAccount($accountType, $phone) : null;

        // Always return success to callers: never disclose whether a phone belongs to an account.
        if (! $account) {
            return;
        }

        $code = (string) random_int(100000, 999999);
        DB::table('phone_account_recoveries')->where([
            'account_type' => $accountType,
            'account_id' => $account->getKey(),
        ])->delete();

        DB::table('phone_account_recoveries')->insert([
            'account_type' => $accountType,
            'account_id' => $account->getKey(),
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->sms->send($phone, "MindMeet: tu código de recuperación es {$code}. Vence en 10 minutos.");
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el OTP de recuperación por SMS.', [
                'account_type' => $accountType,
                'account_id' => $account->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function verify(string $accountType, string $phone, string $code): ?string
    {
        $phone = PatientIdentity::normalizePhone($phone);
        $account = $phone ? $this->findAccount($accountType, $phone) : null;
        if (! $account) {
            return null;
        }

        $recovery = DB::table('phone_account_recoveries')->where([
            'account_type' => $accountType,
            'account_id' => $account->getKey(),
            'phone' => $phone,
        ])->first();

        if (! $recovery || $recovery->verified_at || now()->greaterThan($recovery->expires_at) || $recovery->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, $recovery->code_hash)) {
            DB::table('phone_account_recoveries')->where('id', $recovery->id)->increment('attempts');
            return null;
        }

        $token = Str::random(80);
        DB::table('phone_account_recoveries')->where('id', $recovery->id)->update([
            'verified_at' => now(),
            'recovery_token_hash' => Hash::make($token),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function complete(string $accountType, string $recoveryToken, string $email, string $password): bool
    {
        $recovery = DB::table('phone_account_recoveries')
            ->where('account_type', $accountType)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get()
            ->first(fn ($item) => Hash::check($recoveryToken, $item->recovery_token_hash));

        if (! $recovery) {
            return false;
        }

        $account = $this->findAccountById($accountType, $recovery->account_id);
        if (! $account) {
            return false;
        }

        $email = mb_strtolower(trim($email));
        if ($this->emailIsUsedByAnotherAccount($accountType, $email, $account->getKey())) {
            throw new \DomainException('El correo ya está en uso.');
        }

        DB::transaction(function () use ($account, $email, $password, $recovery) {
            $changes = ['email' => $email, 'password' => Hash::make($password), 'remember_token' => Str::random(60)];
            if ($account instanceof User) {
                $changes['email_verified_at'] = null;
            }
            $account->forceFill($changes)->save();
            $account->tokens()->delete();
            DB::table('phone_account_recoveries')->where('id', $recovery->id)->delete();
        });

        if ($account instanceof User) {
            try {
                $account->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                Log::warning('No se pudo enviar la verificación al nuevo correo.', ['user_id' => $account->id]);
            }
        }

        try {
            $this->sms->send($recovery->phone, 'MindMeet: tu correo y contraseña se actualizaron. Si no fuiste tú, contacta a soporte de inmediato.');
        } catch (\Throwable) {
            // The account change is valid even when the notification provider is unavailable.
        }

        return true;
    }

    private function findAccount(string $accountType, string $phone): ?Model
    {
        return match ($accountType) {
            'user' => User::where('recovery_phone', $phone)->first(),
            'patient' => Patient::where('phone', $phone)->first(),
            default => null,
        };
    }

    private function findAccountById(string $accountType, int $id): ?Model
    {
        return match ($accountType) {
            'user' => User::find($id),
            'patient' => Patient::find($id),
            default => null,
        };
    }

    private function emailIsUsedByAnotherAccount(string $accountType, string $email, int $accountId): bool
    {
        $model = $accountType === 'user' ? User::class : Patient::class;

        return $model::whereRaw('LOWER(email) = ?', [$email])
            ->whereKeyNot($accountId)
            ->exists();
    }
}
