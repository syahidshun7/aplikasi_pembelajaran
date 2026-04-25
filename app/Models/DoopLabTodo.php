<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabTodo extends Model
{
    public const MODE_SELF = 'self';
    public const MODE_MENTOR = 'mentor';

    protected $table = 'dooplab_todos';

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'start_at',
        'deadline',
        'notify_deadline_email',
        'deadline_reminded_at',
        'assignment_mode',
        'owner_user_id',
        'mentor_user_id',
        'is_completed',
        'completed_at',
        'completed_by_user_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'deadline' => 'datetime',
        'notify_deadline_email' => 'boolean',
        'deadline_reminded_at' => 'datetime',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $todo): void {
            if (empty($todo->uuid)) {
                $todo->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function notes()
    {
        return $this->hasMany(DoopLabTodoNote::class, 'todo_id');
    }

    public function isMentorAssigned(): bool
    {
        return (string) $this->assignment_mode === self::MODE_MENTOR;
    }

    public function canToggleBy(User $user): bool
    {
        if ($this->isMentorAssigned()) {
            return $user->isMentor() && (int) $this->mentor_user_id === (int) $user->id;
        }

        return (int) $this->owner_user_id === (int) $user->id;
    }

    public function canDeleteBy(User $user): bool
    {
        if ($this->isMentorAssigned()) {
            return $user->isMentor() && (int) $this->mentor_user_id === (int) $user->id;
        }

        return (int) $this->owner_user_id === (int) $user->id;
    }

    public function canEditBy(User $user): bool
    {
        if ($this->isMentorAssigned()) {
            return $user->isMentor() && (int) $this->mentor_user_id === (int) $user->id;
        }

        return (int) $this->owner_user_id === (int) $user->id;
    }

    public function canCommentBy(User $user): bool
    {
        if ((int) $this->owner_user_id === (int) $user->id) {
            return true;
        }

        if ($this->isMentorAssigned()) {
            return $user->isMentor() && (int) $this->mentor_user_id === (int) $user->id;
        }

        return false;
    }
}
