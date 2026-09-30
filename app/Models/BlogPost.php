<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'author_name',
        'author_user_id',
        'category',
        'category_id',
        'tags',
        'sources',
        'cover_image_url',
        'cover_image_public_id',
        'cover_image_alt',
        'meta_title',
        'meta_description',
        'status',
        'submitted_for_review_at',
        'reviewed_at',
        'review_feedback',
        'changes_requested_at',
        'is_featured',
        'views_count',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'sources' => 'array',
        'is_featured' => 'boolean',
        'views_count' => 'integer',
        'published_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'changes_requested_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
