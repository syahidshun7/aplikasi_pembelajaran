<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DoopNewsPost extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const CATEGORY_ANNOUNCEMENT = 'announcement';
    public const CATEGORY_EVENT = 'event';
    public const CATEGORY_SHOP_ITEM = 'shop_item';
    public const CATEGORY_CLASS = 'class';
    public const CATEGORY_QUEST = 'quest';
    public const CATEGORY_APP_UPDATE = 'app_update';
    public const CATEGORY_COMMUNITY = 'community';

    protected $fillable = [
        'uuid',
        'slug',
        'author_id',
        'reviewer_id',
        'title',
        'category',
        'status',
        'excerpt',
        'body',
        'cover_image_path',
        'version_label',
        'action_label',
        'action_url',
        'submitted_at',
        'reviewed_at',
        'published_at',
        'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DoopNewsPost $post): void {
            $post->uuid = $post->uuid ?: (string) Str::uuid();
            $post->slug = $post->slug ?: static::uniqueSlug((string) $post->title);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public static function categories(): array
    {
        return [
            self::CATEGORY_ANNOUNCEMENT,
            self::CATEGORY_EVENT,
            self::CATEGORY_SHOP_ITEM,
            self::CATEGORY_CLASS,
            self::CATEGORY_QUEST,
            self::CATEGORY_APP_UPDATE,
            self::CATEGORY_COMMUNITY,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ];
    }

    private static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'doopnews';
        $slug = $base;
        $counter = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
