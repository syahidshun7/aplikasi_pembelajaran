<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DailyQuestDefinition extends Model
{
    public const ACTIVITY_LOGIN = 'login';
    public const ACTIVITY_QUEST_SUBMISSION = 'quest_submission';
    public const ACTIVITY_EVENT_ATTENDANCE = 'event_attendance';

    protected $fillable = [
        'code',
        'title',
        'description',
        'activity_type',
        'target_value',
        'reward_exp',
        'reward_gold',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'target_value' => 'integer',
        'reward_exp' => 'integer',
        'reward_gold' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function dailyQuests()
    {
        return $this->hasMany(DailyQuest::class);
    }
}
