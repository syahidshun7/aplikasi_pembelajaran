<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DailyQuest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid',
        'daily_quest_definition_id',
        'user_id',
        'quest_date',
        'title',
        'description',
        'activity_type',
        'target_value',
        'progress_value',
        'reward_exp',
        'reward_gold',
        'sort_order',
        'status',
        'completed_at',
        'claimed_at',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'quest_date' => 'date',
        'target_value' => 'integer',
        'progress_value' => 'integer',
        'reward_exp' => 'integer',
        'reward_gold' => 'integer',
        'sort_order' => 'integer',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyQuest $dailyQuest) {
            if (empty($dailyQuest->uuid)) {
                $dailyQuest->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function definition()
    {
        return $this->belongsTo(DailyQuestDefinition::class, 'daily_quest_definition_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
