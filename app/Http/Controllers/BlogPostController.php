<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::query()
            ->published()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
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
        $payload['categories'] = BlogPost::query()
            ->published()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        return response()->json($payload);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::query()->published()->where('slug', $slug)->firstOrFail();

        $related = BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->where(function ($query) use ($post) {
                if ($post->category) {
                    $query->where('category', $post->category);
                }

                foreach ($post->tags ?? [] as $tag) {
                    $query->orWhereJsonContains('tags', $tag);
                }
            })
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (BlogPost $relatedPost) => $this->serialize($relatedPost, false));

        return response()->json([
            'data' => $this->serialize($post, true),
            'related' => $related,
        ]);
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
            'category' => $post->category,
            'tags' => $post->tags ?? [],
            'cover_image_url' => $post->cover_image_url,
            'cover_image_alt' => $post->cover_image_alt ?: $post->title,
            'meta_title' => $post->meta_title ?: $post->title,
            'meta_description' => $post->meta_description ?: $post->excerpt,
            'is_featured' => $post->is_featured,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'reading_time' => max(1, (int) ceil($words / 220)),
        ];

        if ($includeContent) {
            $data['content'] = $post->content;
        }

        return $data;
    }
}
