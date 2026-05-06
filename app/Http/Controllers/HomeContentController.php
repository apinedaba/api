<?php

namespace App\Http\Controllers;

use App\Services\HomeContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use InvalidArgumentException;
use Inertia\Inertia;

class HomeContentController extends Controller
{
    public function __construct(
        protected HomeContentService $homeContentService
    ) {
    }

    public function index()
    {
        $home = $this->homeContentService->read();

        return Inertia::render('HomeContent', [
            'editor' => $this->homeContentService->splitForEditor($home),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hero' => ['nullable', 'string'],
            'homeSlider' => ['required', 'string'],
            'promotions' => ['required', 'string'],
            'psicoPlus' => ['required', 'string'],
            'sections' => ['required', 'string'],
            'extraBlocks' => ['required', 'string'],
        ]);

        try {
            $home = $this->homeContentService->updateFromPayload($data);
            $this->homeContentService->write($home);
        } catch (InvalidArgumentException $exception) {
            return Redirect::back()->withErrors([
                'json' => $exception->getMessage(),
            ])->withInput();
        }

        return Redirect::route('home-content.index')->with('success', 'Contenido del home actualizado correctamente.');
    }
}
