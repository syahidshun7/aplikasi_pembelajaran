<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuestAttemptSession extends Model
{
    protected $fillable = [
        'user_id',
        'quest_id',
        'attempt_number',
        'submission_token',
        'started_at',
        'expires_at',
        'draft_answers',
        'draft_content',
        'draft_saved_at',
        'submitted_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'draft_answers' => 'array',
        'draft_saved_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];
}
