<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserContentRead extends Model
{
    public const TYPE_QUEST = 'quest';
    public const TYPE_GUIDE = 'guide';
    public const TYPE_EVENT = 'event';
    public const TYPE_STUDY_GROUP = 'study_group';
    public const TYPE_DOOP_NEWS = 'doop_news';

    protected $fillable = [
        'user_id',
        'content_type',
        'content_id',
        'seen_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public static function seenContentIds(int $userId, string $contentType, array $contentIds): Collection
    {
        $ids = collect($contentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($userId <= 0 || $ids->isEmpty()) {
            return collect();
        }

        return static::query()
            ->where('user_id', $userId)
            ->where('content_type', $contentType)
            ->whereIn('content_id', $ids->all())
            ->whereNotNull('seen_at')
            ->pluck('content_id')
            ->map(fn ($id) => (int) $id);
    }

    public static function markSeen(int $userId, string $contentType, int $contentId): void
    {
        if ($userId <= 0 || $contentId <= 0) {
            return;
        }

        static::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'content_type' => $contentType,
                'content_id' => $contentId,
            ],
            [
                'seen_at' => now(),
            ],
        );
    }
}
