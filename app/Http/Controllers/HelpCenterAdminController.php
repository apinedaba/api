<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterAdminController extends Controller
{
    public function index(): Response
    {
        $articles = HelpCenterArticle::query()
            ->orderBy('category_key')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (HelpCenterArticle $article) => $this->transformArticle($article));

        return Inertia::render('HelpCenter', [
            'articles' => $articles,
            'categories' => HelpCenterArticle::categoryOptions(),
            'supportWhatsappUrl' => config('app.help_whatsapp_url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        HelpCenterArticle::create($this->preparePayload($payload));

        return redirect()
            ->route('help-center.index')
            ->with('status', 'Artículo creado correctamente.');
    }

    public function update(Request $request, HelpCenterArticle $helpCenterArticle): RedirectResponse
    {
        $payload = $this->validatePayload($request, $helpCenterArticle->id);

        $helpCenterArticle->update($this->preparePayload($payload, $helpCenterArticle));

        return redirect()
            ->route('help-center.index')
            ->with('status', 'Artículo actualizado correctamente.');
    }

    public function destroy(HelpCenterArticle $helpCenterArticle): RedirectResponse
    {
        $helpCenterArticle->delete();

        return redirect()
            ->route('help-center.index')
            ->with('status', 'Artículo eliminado.');
    }

    protected function validatePayload(Request $request, ?int $articleId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:help_center_articles,slug,' . ($articleId ?: 'NULL') . ',id'],
            'category_key' => ['required', 'string', 'max:60'],
            'summary' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'estimated_read_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }

    protected function preparePayload(array $payload, ?HelpCenterArticle $article = null): array
    {
        $categoryMeta = collect(HelpCenterArticle::categoryOptions())
            ->firstWhere('key', $payload['category_key']);

        return [
            'title' => trim($payload['title']),
            'slug' => Str::slug($payload['slug'] ?: $payload['title']),
            'category_key' => $payload['category_key'],
            'category_name' => $categoryMeta['name'] ?? ($article?->category_name ?? 'Sin categoría'),
            'summary' => trim((string) ($payload['summary'] ?? '')),
            'body' => trim($payload['body']),
            'estimated_read_minutes' => (int) ($payload['estimated_read_minutes'] ?? 4),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_published' => (bool) ($payload['is_published'] ?? false),
        ];
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
            'is_published' => $article->is_published,
            'updated_at' => optional($article->updated_at)->format('Y-m-d H:i'),
        ];
    }
}
