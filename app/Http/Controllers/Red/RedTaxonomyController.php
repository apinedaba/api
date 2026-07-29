<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\RedCategory;
use App\Models\RedPregunta;
use App\Models\RedTag;
use Illuminate\Http\JsonResponse;

class RedTaxonomyController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = RedCategory::query()
            ->where('is_active', true)
            ->withCount(['preguntas' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $tags = RedTag::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (RedTag $tag) => [
                ...$tag->only(['id', 'name', 'slug']),
                'questions_count' => RedPregunta::where('is_active', true)
                    ->whereJsonContains('tags', $tag->name)
                    ->count(),
            ])
            ->sortByDesc('questions_count')
            ->values();

        return response()->json([
            'data' => [
                'categories' => $categories,
                'tags' => $tags,
            ],
        ]);
    }
}
