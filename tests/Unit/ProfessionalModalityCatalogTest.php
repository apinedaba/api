<?php

namespace Tests\Unit;

use App\Http\Controllers\CatalogosController;
use App\Http\Controllers\ProfessionalController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProfessionalModalityCatalogTest extends TestCase
{
    public function test_it_exposes_online_and_in_person_as_public_filter_options(): void
    {
        $filter = (new CatalogosController())->modalidad();

        $this->assertSame('modalidad', $filter['key']);
        $this->assertSame('checkbox', $filter['type']);
        $this->assertSame([
            ['label' => 'Online', 'value' => 'online'],
            ['label' => 'Presencial', 'value' => 'presencial'],
        ], $filter['values']);
    }

    public function test_online_and_in_person_filters_include_mixed_professionals(): void
    {
        $method = new ReflectionMethod(ProfessionalController::class, 'compatibleFormats');
        $formats = $method->invoke(new ProfessionalController(), ['ONLINE', 'presencial']);

        $this->assertSame(['online', 'mixto', 'mixta', 'presencial'], $formats);
    }
}
