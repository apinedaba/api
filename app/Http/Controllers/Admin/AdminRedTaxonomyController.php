<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedCategory;
use App\Models\RedPregunta;
use App\Models\RedTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminRedTaxonomyController extends Controller
{
    public function index(): Response
    {
        $categories = RedCategory::withCount('preguntas')->orderBy('sort_order')->orderBy('name')->get();
        $tags = RedTag::orderBy('sort_order')->orderBy('name')->get()->map(fn (RedTag $tag) => [
            ...$tag->toArray(),
            'questions_count' => RedPregunta::whereJsonContains('tags', $tag->name)->count(),
        ]);

        return Inertia::render('Minder/ForumTaxonomy', compact('categories', 'tags'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        RedCategory::create([...$data, 'slug' => Str::slug($data['name'])]);

        return back()->with('success', 'Categoría creada.');
    }

    public function updateCategory(Request $request, RedCategory $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);
        $category->update([...$data, 'slug' => Str::slug($data['name'])]);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroyCategory(RedCategory $category): RedirectResponse
    {
        abort_if($category->preguntas()->exists(), 422, 'No puedes eliminar una categoría que ya tiene preguntas.');
        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $data = $this->validateTag($request);
        RedTag::create([...$data, 'slug' => Str::slug($data['name'])]);

        return back()->with('success', 'Etiqueta creada.');
    }

    public function updateTag(Request $request, RedTag $tag): RedirectResponse
    {
        $data = $this->validateTag($request, $tag);
        $oldName = $tag->name;
        $tag->update([...$data, 'slug' => Str::slug($data['name'])]);

        if ($oldName !== $data['name']) {
            RedPregunta::whereJsonContains('tags', $oldName)->get()->each(function (RedPregunta $question) use ($oldName, $data) {
                $question->update([
                    'tags' => collect($question->tags)->map(fn ($name) => $name === $oldName ? $data['name'] : $name)->values()->all(),
                ]);
            });
        }

        return back()->with('success', 'Etiqueta actualizada.');
    }

    public function destroyTag(RedTag $tag): RedirectResponse
    {
        abort_if(RedPregunta::whereJsonContains('tags', $tag->name)->exists(), 422, 'No puedes eliminar una etiqueta que ya está en uso.');
        $tag->delete();

        return back()->with('success', 'Etiqueta eliminada.');
    }

    private function validateCategory(Request $request, ?RedCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('red_categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:240'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateTag(Request $request, ?RedTag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('red_tags')->ignore($tag)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
