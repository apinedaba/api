<?php

namespace App\Support;

use Illuminate\Support\Str;

final class MexicoGeography
{
    private const STATES = [
        'Aguascalientes' => ['AGS'], 'Baja California' => ['BC'], 'Baja California Sur' => ['BCS'],
        'Campeche' => ['CAMP'], 'Chiapas' => ['CHIS'], 'Chihuahua' => ['CHIH'],
        'Ciudad de México' => ['CDMX', 'Ciudad De México', 'Distrito Federal', 'DF'],
        'Coahuila' => ['COAH', 'Coahuila de Zaragoza'], 'Colima' => ['COL'], 'Durango' => ['DGO'],
        'Estado de México' => ['MEX', 'Estado De México', 'Estado de Mexico'],
        'Guanajuato' => ['GTO'], 'Guerrero' => ['GRO'], 'Hidalgo' => ['HGO'], 'Jalisco' => ['JAL'],
        'Michoacán' => ['MICH', 'Michoacán de Ocampo', 'Michoacan'], 'Morelos' => ['MOR'],
        'Nayarit' => ['NAY'], 'Nuevo León' => ['NL', 'NLE', 'Nuevo Leon'], 'Oaxaca' => ['OAX'],
        'Puebla' => ['PUE'], 'Querétaro' => ['QRO', 'Querétaro de Arteaga', 'Queretaro'],
        'Quintana Roo' => ['QROO'], 'San Luis Potosí' => ['SLP', 'San Luis Potosi'],
        'Sinaloa' => ['SIN'], 'Sonora' => ['SON'], 'Tabasco' => ['TAB'],
        'Tamaulipas' => ['TAMPS'], 'Tlaxcala' => ['TLAX'],
        'Veracruz' => ['VER', 'Veracruz de Ignacio de la Llave'],
        'Yucatán' => ['YUC', 'Yucatan'], 'Zacatecas' => ['ZAC'],
    ];

    public static function canonicalState(?string $value): ?string
    {
        $key = self::key($value);
        if ($key === '') return null;

        foreach (self::STATES as $canonical => $aliases) {
            foreach ([$canonical, ...$aliases] as $candidate) {
                if (self::key($candidate) === $key) return $canonical;
            }
        }

        return trim((string) $value);
    }

    public static function stateAliases(?string $value): array
    {
        $canonical = self::canonicalState($value);
        if (!$canonical) return [];

        return array_values(array_unique([$canonical, ...(self::STATES[$canonical] ?? []), trim((string) $value)]));
    }

    public static function expandStateFilters(array $values): array
    {
        return collect($values)->flatMap(fn ($value) => self::stateAliases((string) $value))->unique()->values()->all();
    }

    private static function key(?string $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();
    }
}
