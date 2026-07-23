<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\User;

class ProfessionalContact
{
    public static function publicName(User $professional): string
    {
        $name = data_get($professional->contacto, 'publicname')
            ?: data_get($professional->contacto, 'publicName')
            ?: self::profile($professional)?->publicName
            ?: $professional->name
            ?: 'tu profesional';

        return self::templateText((string) $name);
    }

    public static function whatsapp(User $professional): ?string
    {
        $phone = data_get($professional->contacto, 'whatsapp')
            ?: data_get($professional->contacto, 'telefono')
            ?: data_get($professional->contacto, 'phone');

        if ($phone) {
            return (string) $phone;
        }

        $profile = self::profile($professional);
        $phone = $profile?->whatsapp ?: $profile?->movil;

        return $phone ? (string) $phone : null;
    }

    public static function templateText(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }

    private static function profile(User $professional): ?Profile
    {
        return Profile::query()->where('user_id', $professional->id)->latest('id')->first();
    }
}
