<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminBlogPostController extends Controller
{
    public function index()
    {
        return Inertia::render('BlogPosts', [
            'posts' => BlogPost::query()
                ->latest('updated_at')
                ->get()
                ->map(fn (BlogPost $post) => $this->serialize($post)),
            'categories' => BlogPost::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $this->normalize($data);
        $this->attachUploadedImage($request, $data);
        BlogPost::create($data);

        return Redirect::route('blog-posts.index')->with('success', 'Artículo creado correctamente.');
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $data = $this->validatedData($request, $blogPost);
        $this->normalize($data, $blogPost);
        $this->attachUploadedImage($request, $data);
        $blogPost->update($data);

        return Redirect::route('blog-posts.index')->with('success', 'Artículo actualizado correctamente.');
    }

    public function destroy(BlogPost $blogPost)
    {
        $blogPost->delete();

        return Redirect::route('blog-posts.index')->with('success', 'Artículo eliminado correctamente.');
    }

    private function validatedData(Request $request, ?BlogPost $blogPost = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('blog_posts', 'slug')->ignore($blogPost?->id)],
            'excerpt' => ['required', 'string', 'max:600'],
            'content' => ['required', 'string'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'cover_image_url' => ['nullable', 'url', 'max:1000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'cover_image_alt' => ['nullable', 'string', 'max:180'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function normalize(array &$data, ?BlogPost $blogPost = null): void
    {
        unset($data['cover_image']);
        $baseSlug = Str::slug($data['slug'] ?: $data['title']);
        $data['slug'] = $baseSlug;
        $suffix = 2;
        while (BlogPost::withTrashed()
            ->where('slug', $data['slug'])
            ->when($blogPost, fn ($query) => $query->whereKeyNot($blogPost->id))
            ->exists()) {
            $data['slug'] = "{$baseSlug}-{$suffix}";
            $suffix++;
        }
        $data['author_name'] = $data['author_name'] ?: 'Equipo MindMeet';
        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = $blogPost?->published_at ?? now();
        }
    }

    private function attachUploadedImage(Request $request, array &$data): void
    {
        if (!$request->hasFile('cover_image')) {
            return;
        }

        $result = (new UploadApi())->upload($request->file('cover_image')->getRealPath(), [
            'folder' => 'mindmeet-blog',
            'resource_type' => 'image',
        ]);

        $data['cover_image_url'] = $result['secure_url'] ?? $data['cover_image_url'] ?? null;
        $data['cover_image_public_id'] = $result['public_id'] ?? null;
    }

    private function serialize(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'author_name' => $post->author_name,
            'category' => $post->category,
            'tags' => implode(', ', $post->tags ?? []),
            'cover_image_url' => $post->cover_image_url,
            'cover_image_alt' => $post->cover_image_alt,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'status' => $post->status,
            'is_featured' => $post->is_featured,
            'published_at' => optional($post->published_at)->format('Y-m-d\TH:i'),
            'updated_at' => optional($post->updated_at)->format('d/m/Y H:i'),
        ];
    }
}
