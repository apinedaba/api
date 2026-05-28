<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class TemporalityContentController extends Controller
{
    /**
     * Mostrar editor de contenido para una temporalidad
     */
    public function edit(string $sectionKey, int $temporalityId)
    {
        return Inertia::render('TemporalityContentEditor', [
            'sectionKey' => $sectionKey,
            'temporalityId' => $temporalityId,
        ]);
    }
}
