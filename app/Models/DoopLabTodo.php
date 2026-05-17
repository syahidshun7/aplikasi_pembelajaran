<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabTodo extends Model
{
    public const MODE_SELF = 'self';
    public const MODE_MENTOR = 'mentor';

    public const MILESTONE_TASK = 'task';
    public const MILESTONE_MILESTONE = 'milestone';
    public const MILESTONE_CHECKPOINT = 'checkpoint';
    public const MILESTONE_LOGBOOK = 'logbook';

    public const STATUS_TODO = 'todo';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DONE = 'done';

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
        'milestone_type',
        'workflow_status',
        'owner_user_id',
        'mentor_user_id',
        'creation_id',
        'is_completed',
        'completed_at',
        'completed_by_user_id',
        'review_requested_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'review_note',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'deadline' => 'datetime',
        'notify_deadline_email' => 'boolean',
        'deadline_reminded_at' => 'datetime',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'review_requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $todo): void {
            if (empty($todo->uuid)) {
                $todo->uuid = (string) Str::uuid();
            }

            if (! $todo->workflow_status) {
                $todo->workflow_status = self::STATUS_TODO;
            }

            if (! $todo->milestone_type) {
                $todo->milestone_type = self::MILESTONE_TASK;
            }
        });

        static::saving(function (self $todo): void {
            $workflow = (string) ($todo->workflow_status ?: self::STATUS_TODO);
            $todo->is_completed = in_array($workflow, [self::STATUS_DONE, self::STATUS_APPROVED], true);

            if ($todo->is_completed) {
                $todo->completed_at = $todo->completed_at ?: now();
            } elseif ($todo->isDirty('workflow_status')) {
                $todo->completed_at = null;
                $todo->completed_by_user_id = null;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function milestoneOptions(): array
    {
        return [
            self::MILESTONE_TASK,
            self::MILESTONE_MILESTONE,
            self::MILESTONE_CHECKPOINT,
            self::MILESTONE_LOGBOOK,
        ];
    }

    public static function workflowOptions(): array
    {
        return [
            self::STATUS_TODO,
            self::STATUS_ONGOING,
            self::STATUS_BLOCKED,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_DONE,
        ];
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

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function creation()
    {
        return $this->belongsTo(Creation::class, 'creation_id');
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
            return ((int) $this->owner_user_id === (int) $user->id)
                || ($user->isMentor() && (int) $this->mentor_user_id === (int) $user->id);
        }

        return (int) $this->owner_user_id === (int) $user->id;
    }

    public function canEditBy(User $user): bool
    {
        if ($this->isMentorAssigned()) {
            return ((int) $this->owner_user_id === (int) $user->id)
                || ($user->isMentor() && (int) $this->mentor_user_id === (int) $user->id);
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

    public function canSubmitCheckpointBy(User $user): bool
    {
        return (int) $this->owner_user_id === (int) $user->id && $this->isMentorAssigned();
    }

    public function canReviewCheckpointBy(User $user): bool
    {
        return $user->isMentor() && (int) $this->mentor_user_id === (int) $user->id && $this->isMentorAssigned();
    }
}
