<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\RedCategory;
use App\Models\RedPregunta;
use App\Models\RedTag;
use App\Models\MindmeetSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedTaxonomyController extends Controller
{
    public function index(Request $request): JsonResponse
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
                'can_announce' => (int) data_get(
                    MindmeetSetting::valueFor('forum_announcement_publisher'),
                    'user_id',
                    0
                ) === (int) $request->user()->id,
            ],
        ]);
    }
}
