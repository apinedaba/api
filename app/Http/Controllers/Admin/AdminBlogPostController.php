<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\User;
use App\Notifications\BlogPostReviewNotification;
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
            'posts' => BlogPost::query()->with(['categoryRelation', 'author'])
                ->latest('updated_at')
                ->get()
                ->map(fn (BlogPost $post) => $this->serialize($post)),
            'categories' => BlogCategory::query()->withCount('posts')->orderBy('name')->get()
                ->map(fn (BlogCategory $category) => $this->serializeCategory($category)),
            'blogAuthors' => User::query()
                ->where('can_publish_blog', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'image', 'contacto', 'blog_access_enabled_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->contacto['publicName'] ?? $user->name,
                    'legal_name' => $user->name,
                    'email' => $user->email,
                    'image' => $user->image,
                    'enabled_at' => optional($user->blog_access_enabled_at)->format('d/m/Y H:i'),
                ]),
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
        $previousStatus = $blogPost->status;
        $data = $this->validatedData($request, $blogPost);
        $this->normalize($data, $blogPost);
        $this->attachUploadedImage($request, $data);
        $blogPost->update($data);

        if ($blogPost->author && $previousStatus !== $blogPost->status) {
            if ($blogPost->status === 'published') {
                $blogPost->author->notify(new BlogPostReviewNotification($blogPost, 'published'));
            } elseif ($blogPost->status === 'changes_requested') {
                $blogPost->author->notify(new BlogPostReviewNotification($blogPost, 'changes_requested'));
            }
        }

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
            'author_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('can_publish_blog', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')],
            'tags' => ['nullable', 'string', 'max:1000'],
            'sources' => ['nullable', 'string', 'max:5000'],
            'cover_image_url' => ['nullable', 'url', 'max:1000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'cover_image_alt' => ['nullable', 'string', 'max:180'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::in(['draft', 'pending_review', 'changes_requested', 'published'])],
            'review_feedback' => ['nullable', 'required_if:status,changes_requested', 'string', 'max:2000'],
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
        if (! empty($data['author_user_id'])) {
            $author = User::findOrFail($data['author_user_id']);
            $data['author_name'] = $author->contacto['publicName'] ?? $author->name;
        } else {
            $data['author_user_id'] = null;
            $data['author_name'] = $data['author_name'] ?: 'Equipo MindMeet';
        }
        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $data['sources'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['sources'] ?? '')))
            ->map(fn ($source) => trim($source))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($data['status'] === 'pending_review' && $blogPost?->status !== 'pending_review') {
            $data['submitted_for_review_at'] = now();
        }

        if ($data['status'] === 'published') {
            $data['reviewed_at'] = now();
            $data['review_feedback'] = null;
            $data['changes_requested_at'] = null;
        }

        if ($data['status'] === 'changes_requested') {
            $data['changes_requested_at'] = now();
            $data['reviewed_at'] = now();
            $data['published_at'] = null;
        }

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
            'author_user_id' => $post->author_user_id,
            'author' => $post->author ? [
                'id' => $post->author->id,
                'name' => $post->author_name,
                'image' => $post->author->image,
            ] : null,
            'category' => $post->categoryRelation ? $this->serializeCategory($post->categoryRelation) : null,
            'category_id' => $post->category_id,
            'tags' => implode(', ', $post->tags ?? []),
            'sources' => implode("\n", $post->sources ?? []),
            'cover_image_url' => $post->cover_image_url,
            'cover_image_alt' => $post->cover_image_alt,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'status' => $post->status,
            'submitted_for_review_at' => optional($post->submitted_for_review_at)->format('d/m/Y H:i'),
            'reviewed_at' => optional($post->reviewed_at)->format('d/m/Y H:i'),
            'review_feedback' => $post->review_feedback,
            'changes_requested_at' => optional($post->changes_requested_at)->format('d/m/Y H:i'),
            'is_featured' => $post->is_featured,
            'published_at' => optional($post->published_at)->format('Y-m-d\TH:i'),
            'updated_at' => optional($post->updated_at)->format('d/m/Y H:i'),
        ];
    }

    private function serializeCategory(BlogCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'posts_count' => $category->posts_count,
        ];
    }
}
