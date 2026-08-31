<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use App\Models\Administrator;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CredentialService
{
    public function forPsychologist(User $user): array
    {
        $isPublic = User::query()->publiclyVisible()->whereKey($user->id)->exists();
        $publicUrl = $isPublic ? $this->publicProfileUrl($user) : null;

        return [
            'credentialId' => $this->credentialId($user, 'PSI'),
            'fullName' => trim((string) $user->name) ?: 'Profesional MindMeet',
            'photoUrl' => $this->safeImageUrl($user->image),
            'userType' => 'psychologist',
            'genderLabel' => $this->genderLabel(data_get($user->personales, 'genero')),
            'verified' => $user->identity_verification_status === 'approved',
            'status' => $user->activo ? 'active' : 'suspended',
            'publicProfileUrl' => $publicUrl,
        ];
    }

    public function forPatient(Patient $patient): array
    {
        return [
            'credentialId' => $this->credentialId($patient, 'PAC'),
            'fullName' => trim((string) $patient->name) ?: 'Paciente MindMeet',
            'photoUrl' => $this->safeImageUrl($patient->image),
            'userType' => 'patient',
            'genderLabel' => null,
            'verified' => false,
            'status' => in_array($patient->status, ['revoked', 'suspended'], true) ? $patient->status : ($patient->activo === false ? 'suspended' : 'active'),
            'publicProfileUrl' => null,
        ];
    }

    public function forAdministrator(Administrator $administrator): array
    {
        return [
            'credentialId' => $this->credentialId($administrator, 'ADM'),
            'fullName' => trim((string) $administrator->name) ?: 'Superadmin MindMeet',
            'photoUrl' => $this->safeImageUrl($administrator->image),
            'userType' => 'superadmin',
            'genderLabel' => null,
            'verified' => true,
            'status' => 'active',
            'publicProfileUrl' => null,
            'qrSvg' => null,
        ];
    }

    public function qrSvgDataUri(string $url): string
    {
        if (! $this->isOfficialPublicUrl($url)) {
            throw new \DomainException('Tu perfil público debe estar activo para generar el código QR.');
        }

        $svg = Builder::create()->writer(new SvgWriter())->data($url)->size(720)->margin(12)->build()->getString();

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function isOfficialPublicUrl(?string $url): bool
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) return false;

        $parts = parse_url($url);
        return ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, ['mindmeet.com.mx', 'www.mindmeet.com.mx'], true)
            && str_starts_with($parts['path'] ?? '', '/psicologos/');
    }

    private function credentialId(Model $model, string $type): string
    {
        if (! $model->credential_public_id) {
            do {
                $value = 'MM-' . $type . '-' . Str::upper(Str::random(14));
            } while ($model->newQuery()->where('credential_public_id', $value)->exists());

            $model->forceFill(['credential_public_id' => $value])->saveQuietly();
        }

        return $model->credential_public_id;
    }

    private function publicProfileUrl(User $user): string
    {
        $configuredBase = rtrim(config('app.front_url_user') ?: config('app.front_url') ?: config('app.frontend_url') ?: '', '/');
        $configuredHost = parse_url($configuredBase, PHP_URL_HOST);
        // The credential is intended for public verification, so its QR must
        // never point to a local/development frontend.
        $base = in_array($configuredHost, ['mindmeet.com.mx', 'www.mindmeet.com.mx'], true)
            ? $configuredBase
            : 'https://mindmeet.com.mx';
        $slug = Str::slug(data_get($user->contacto, 'publicName') ?: $user->name, '-', 'es');

        return "{$base}/psicologos/{$user->id}/{$slug}";
    }

    private function safeImageUrl(?string $url): ?string
    {
        return $url && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function genderLabel(?string $gender): ?string
    {
        return $gender ? strtolower(trim($gender)) : null;
    }
}
