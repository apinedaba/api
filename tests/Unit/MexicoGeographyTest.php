<?php

namespace Tests\Unit;

use App\Support\MexicoGeography;
use PHPUnit\Framework\TestCase;

class MexicoGeographyTest extends TestCase
{
    /** @dataProvider stateVariants */
    public function test_it_normalizes_state_variants(string $input, string $expected): void
    {
        $this->assertSame($expected, MexicoGeography::canonicalState($input));
    }

    public static function stateVariants(): array
    {
        return [
            ['CDMX', 'Ciudad de México'], ['Cdmx', 'Ciudad de México'],
            ['Ciudad De México', 'Ciudad de México'], ['Distrito Federal', 'Ciudad de México'],
            ['MEX', 'Estado de México'], ['Estado de Mexico', 'Estado de México'],
            ['NL', 'Nuevo León'], ['QRO', 'Querétaro'],
        ];
    }

    public function test_it_expands_aliases_for_historical_records(): void
    {
        $aliases = MexicoGeography::expandStateFilters(['cdmx']);
        $this->assertContains('Ciudad de México', $aliases);
        $this->assertContains('Distrito Federal', $aliases);
        $this->assertContains('DF', $aliases);
    }
}
