<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::query()->with(['categoryRelation', 'author'])
            ->published()
            ->when($request->filled('category'), fn ($query) => $query->whereHas('categoryRelation', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category'))))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($search) use ($term) {
                    $search->where('title', 'like', $term)
                        ->orWhere('excerpt', 'like', $term)
                        ->orWhere('content', 'like', $term);
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        $payload = $posts->through(fn (BlogPost $post) => $this->serialize($post, false))->toArray();
        $payload['categories'] = BlogCategory::query()->orderBy('name')->get()
            ->map(fn (BlogCategory $category) => $this->serializeCategory($category));

        return response()->json($payload);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::query()->with(['categoryRelation', 'author'])->published()->where('slug', $slug)->firstOrFail();

        $related = BlogPost::query()->with(['categoryRelation', 'author'])
            ->published()
            ->whereKeyNot($post->id)
            ->where(function ($query) use ($post) {
                if ($post->category_id) {
                    $query->where('category_id', $post->category_id);
                }

                foreach ($post->tags ?? [] as $tag) {
                    $query->orWhereJsonContains('tags', $tag);
                }
            })
            ->inRandomOrder()
            ->limit(4)
            ->get()
            ->map(fn (BlogPost $relatedPost) => $this->serialize($relatedPost, false));

        $trending = BlogPost::query()
            ->with(['categoryRelation', 'author'])
            ->published()
            ->whereKeyNot($post->id)
            ->orderByDesc('views_count')
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn (BlogPost $trendingPost) => $this->serialize($trendingPost, false));

        $data = $this->serialize($post, true);
        if ($post->author && User::query()->publiclyVisible()->whereKey($post->author->id)->exists()) {
            $data['author']['profile_url'] = '/psicologos/'.$post->author->id.'/'.str($post->author_name)->slug();
        }

        return response()->json([
            'data' => $data,
            'related' => $related,
            'trending' => $trending,
        ]);
    }

    public function recordView(string $slug): JsonResponse
    {
        $post = BlogPost::query()->published()->where('slug', $slug)->firstOrFail();
        $post->increment('views_count');

        return response()->json(['views_count' => $post->fresh()->views_count]);
    }

    private function serialize(BlogPost $post, bool $includeContent): array
    {
        $words = str_word_count(strip_tags($post->content));
        $data = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'author_name' => $post->author_name,
            'author' => $post->author ? [
                'id' => $post->author->id,
                'name' => $post->author_name,
                'image' => $post->author->image,
            ] : null,
            'category' => $post->categoryRelation ? $this->serializeCategory($post->categoryRelation) : null,
            'tags' => $post->tags ?? [],
            'sources' => $post->sources ?? [],
            'cover_image_url' => $post->cover_image_url,
            'cover_image_alt' => $post->cover_image_alt ?: $post->title,
            'meta_title' => $post->meta_title ?: $post->title,
            'meta_description' => $post->meta_description ?: $post->excerpt,
            'is_featured' => $post->is_featured,
            'views_count' => $post->views_count,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'reading_time' => max(1, (int) ceil($words / 220)),
        ];

        if ($includeContent) {
            $data['content'] = $post->content;
        }

        return $data;
    }

    private function serializeCategory(BlogCategory $category): array
    {
        return ['name' => $category->name, 'slug' => $category->slug, 'description' => $category->description];
    }
}
