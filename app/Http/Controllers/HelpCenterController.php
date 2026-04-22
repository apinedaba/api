<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));

        $articles = HelpCenterArticle::query()
            ->published()
            ->when($category !== '', fn ($query) => $query->where('category_key', $category))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($articleQuery) use ($search) {
                    $articleQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (HelpCenterArticle $article) => $this->transformArticle($article));

        $categories = collect(HelpCenterArticle::categoryOptions())
            ->map(function (array $categoryMeta) use ($articles) {
                $count = $articles->where('category_key', $categoryMeta['key'])->count();

                return [
                    ...$categoryMeta,
                    'count' => $count,
                ];
            })
            ->values();

        return response()->json([
            'data' => $articles->values(),
            'categories' => $categories,
            'support' => [
                'whatsapp_url' => config('app.help_whatsapp_url'),
                'label' => 'Hablar por WhatsApp',
            ],
        ]);
    }

    protected function transformArticle(HelpCenterArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'category_key' => $article->category_key,
            'category_name' => $article->category_name,
            'summary' => $article->summary,
            'body' => $article->body,
            'estimated_read_minutes' => $article->estimated_read_minutes,
            'sort_order' => $article->sort_order,
        ];
    }
}
