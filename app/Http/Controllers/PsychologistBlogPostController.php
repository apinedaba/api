<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Administrator;
use App\Notifications\BlogPostReviewNotification;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class PsychologistBlogPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);

        return response()->json([
            'posts' => BlogPost::query()
                ->with('categoryRelation')
                ->where('author_user_id', $user->id)
                ->latest('updated_at')
                ->get()
                ->map(fn (BlogPost $post) => $this->serialize($post)),
            'categories' => BlogCategory::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'author' => [
                'id' => $user->id,
                'name' => $user->contacto['publicName'] ?? $user->name,
                'image' => $user->image,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);
        $data = $this->validatedData($request);
        $this->normalize($data, $user);
        $this->attachUploadedImage($request, $data);

        $post = BlogPost::create($data)->load('categoryRelation');
        $this->notifyReviewersIfSubmitted($post, null);

        return response()->json([
            'message' => $post->status === 'pending_review'
                ? 'Artículo enviado a revisión de MindMeet.'
                : 'Borrador guardado correctamente.',
            'post' => $this->serialize($post),
        ], 201);
    }

    public function update(Request $request, BlogPost $blogPost): JsonResponse
    {
        $user = $this->authorizedUser($request);
        $this->ensureOwner($blogPost, $user->id);

        abort_if($blogPost->status === 'published', 422, 'Un artículo publicado debe ser editado por MindMeet.');

        $previousStatus = $blogPost->status;
        $data = $this->validatedData($request, $blogPost);
        $this->normalize($data, $user, $blogPost);
        $this->attachUploadedImage($request, $data);
        $blogPost->update($data);
        $this->notifyReviewersIfSubmitted($blogPost, $previousStatus);

        return response()->json([
            'message' => $blogPost->status === 'pending_review'
                ? 'Cambios enviados a revisión de MindMeet.'
                : 'Borrador actualizado correctamente.',
            'post' => $this->serialize($blogPost->fresh('categoryRelation')),
        ]);
    }

    public function destroy(Request $request, BlogPost $blogPost): JsonResponse
    {
        $user = $this->authorizedUser($request);
        $this->ensureOwner($blogPost, $user->id);
        abort_if($blogPost->status === 'published', 422, 'No puedes eliminar un artículo publicado.');

        $blogPost->delete();

        return response()->json(['message' => 'Artículo eliminado correctamente.']);
    }

    private function authorizedUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->can_publish_blog, 403, 'MindMeet todavía no ha habilitado el Blog para tu cuenta.');

        return $user;
    }

    private function ensureOwner(BlogPost $blogPost, int $userId): void
    {
        abort_unless((int) $blogPost->author_user_id === $userId, 404);
    }

    private function validatedData(Request $request, ?BlogPost $blogPost = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('blog_posts', 'slug')->ignore($blogPost?->id)],
            'excerpt' => ['required', 'string', 'max:600'],
            'content' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')],
            'tags' => ['nullable', 'string', 'max:1000'],
            'sources' => ['nullable', 'string', 'max:5000'],
            'cover_image_url' => ['nullable', 'url', 'max:1000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'cover_image_alt' => ['nullable', 'string', 'max:180'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'submit_for_review' => ['nullable', 'boolean'],
        ]);
    }

    private function normalize(array &$data, $user, ?BlogPost $blogPost = null): void
    {
        unset($data['cover_image']);
        $submitForReview = (bool) ($data['submit_for_review'] ?? false);
        unset($data['submit_for_review']);

        $baseSlug = Str::slug($data['slug'] ?: $data['title']) ?: Str::random(10);
        $data['slug'] = $baseSlug;
        $suffix = 2;
        while (BlogPost::withTrashed()->where('slug', $data['slug'])
            ->when($blogPost, fn ($query) => $query->whereKeyNot($blogPost->id))->exists()) {
            $data['slug'] = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        $data['author_user_id'] = $user->id;
        $data['author_name'] = $user->contacto['publicName'] ?? $user->name;
        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->unique()->values()->all();
        $data['sources'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['sources'] ?? '')))->map(fn ($source) => trim($source))->filter()->unique()->values()->all();
        $data['status'] = $submitForReview ? 'pending_review' : ($blogPost?->status === 'pending_review' ? 'pending_review' : 'draft');
        $data['is_featured'] = false;
        $data['published_at'] = null;
        $data['reviewed_at'] = null;
        if ($submitForReview && $blogPost?->status !== 'pending_review') {
            $data['submitted_for_review_at'] = now();
            $data['review_feedback'] = null;
            $data['changes_requested_at'] = null;
        }
    }

    private function attachUploadedImage(Request $request, array &$data): void
    {
        if (! $request->hasFile('cover_image')) {
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
            'category_id' => $post->category_id,
            'category' => $post->categoryRelation?->only(['id', 'name', 'slug']),
            'tags' => implode(', ', $post->tags ?? []),
            'sources' => implode("\n", $post->sources ?? []),
            'cover_image_url' => $post->cover_image_url,
            'cover_image_alt' => $post->cover_image_alt,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'status' => $post->status,
            'review_feedback' => $post->review_feedback,
            'changes_requested_at' => optional($post->changes_requested_at)->toIso8601String(),
            'submitted_for_review_at' => optional($post->submitted_for_review_at)->toIso8601String(),
            'published_at' => optional($post->published_at)->toIso8601String(),
            'updated_at' => optional($post->updated_at)->toIso8601String(),
        ];
    }

    private function notifyReviewersIfSubmitted(BlogPost $post, ?string $previousStatus): void
    {
        if ($post->status !== 'pending_review' || $previousStatus === 'pending_review') {
            return;
        }

        Notification::send(
            Administrator::query()->get(),
            new BlogPostReviewNotification($post, 'submitted')
        );
    }
}
