<?php

namespace App\Models;

use App\Support\DateTimeInput;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quest extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'Available';
    public const STATUS_IN_PROGRESS = 'In-Progress';
    public const STATUS_DONE = 'Done';

    public const SCHEDULE_MANUAL = 'manual';
    public const SCHEDULE_ONCE = 'once';

    public const TYPE_MAIN = 'main';
    public const TYPE_OPTIONAL = 'optional';

    protected $fillable = [
        'uuid',
        'title',
        'status',
        'description',
        'reward_exp',
        'reward_gold',
        'difficulty',
        'quest_type',
        'is_completed',
        'study_group_id',
        'task_bank_id',
        'rubric_id',
        'deadline',
        'schedule_type',
        'available_from',
        'available_until',
    ];

    protected static function booted()
    {
        static::creating(function ($quest) {
            $quest->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

public function submissions()
{

    return $this->hasMany(Submission::class);
}

public function studyGroup()
{
    return $this->belongsTo(StudyGroup::class);
}

public function taskBank()
{
    return $this->belongsTo(TaskBank::class);
}

public function rubric()
{
    return $this->belongsTo(Rubric::class, 'rubric_id');
}

public function events()
{
    return $this->belongsToMany(Event::class, 'event_quest')
        ->withPivot('sort_order')
        ->withTimestamps();
}

    protected $casts = [
        'deadline' => 'datetime',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
    ];

    protected function deadline(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => DateTimeInput::normalizeNullable($value),
        );
    }

    protected function availableFrom(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => DateTimeInput::normalizeNullable($value),
        );
    }

    protected function availableUntil(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => DateTimeInput::normalizeNullable($value),
        );
    }

    public function scopeVisibleForUsers(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $resolvedAt = $at ?? now();

        return $query
            ->where('status', self::STATUS_AVAILABLE)
            ->where(function (Builder $scheduleQuery) use ($resolvedAt) {
                $scheduleQuery
                    ->where(function (Builder $manualQuery) {
                        $manualQuery->whereNull('schedule_type')
                            ->orWhere('schedule_type', self::SCHEDULE_MANUAL);
                    })
                    ->orWhere(function (Builder $onceQuery) use ($resolvedAt) {
                        $onceQuery->where('schedule_type', self::SCHEDULE_ONCE)
                            ->where(function (Builder $timeQuery) use ($resolvedAt) {
                                $timeQuery->whereNull('available_from')
                                    ->orWhere('available_from', '<=', $resolvedAt);
                            })
                            ->where(function (Builder $timeQuery) use ($resolvedAt) {
                                $timeQuery->whereNull('available_until')
                                    ->orWhere('available_until', '>', $resolvedAt);
                            });
                    });
            });
    }

    public function scopeListedForUsers(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $resolvedAt = $at ?? now();

        return $query
            ->where(function (Builder $statusQuery) use ($resolvedAt) {
                $statusQuery
                    // Manual quest wajib tampil di list apa pun statusnya.
                    ->where(function (Builder $manualQuery) {
                        $manualQuery->whereNull('schedule_type')
                            ->orWhere('schedule_type', self::SCHEDULE_MANUAL);
                    })
                    ->orWhere(function (Builder $availableOnceQuery) use ($resolvedAt) {
                        $availableOnceQuery
                            ->where('schedule_type', self::SCHEDULE_ONCE)
                            ->where('status', self::STATUS_AVAILABLE)
                            ->where(function (Builder $timeQuery) use ($resolvedAt) {
                                $timeQuery->whereNull('available_from')
                                    ->orWhere('available_from', '<=', $resolvedAt);
                            })
                            ->where(function (Builder $timeQuery) use ($resolvedAt) {
                                $timeQuery->whereNull('available_until')
                                    ->orWhere('available_until', '>', $resolvedAt);
                            });
                    })
                    ->orWhere(function (Builder $lateDeadlineQuery) use ($resolvedAt) {
                        $lateDeadlineQuery
                        ->where('schedule_type', self::SCHEDULE_ONCE)
                        ->whereIn('status', [self::STATUS_DONE, 'Completed'])
                        ->whereNotNull('deadline')
                        ->where('deadline', '<=', $resolvedAt)
                        ->where(function (Builder $timeQuery) use ($resolvedAt) {
                            $timeQuery->whereNull('available_from')
                                ->orWhere('available_from', '<=', $resolvedAt);
                        })
                        ->where(function (Builder $timeQuery) use ($resolvedAt) {
                            $timeQuery->whereNull('available_until')
                                ->orWhere('available_until', '>', $resolvedAt);
                        });
                    });
            });
    }

    public function scopePublishedForAverage(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $resolvedAt = $at ?? now();

        return $query
            ->where(function (Builder $scheduleQuery) use ($resolvedAt) {
                $scheduleQuery
                    ->where(function (Builder $manualQuery) {
                        $manualQuery->whereNull('schedule_type')
                            ->orWhere('schedule_type', self::SCHEDULE_MANUAL);
                    })
                    ->orWhere(function (Builder $onceQuery) use ($resolvedAt) {
                        $onceQuery->where('schedule_type', self::SCHEDULE_ONCE)
                            ->where(function (Builder $timeQuery) use ($resolvedAt) {
                                $timeQuery->whereNull('available_from')
                                    ->orWhere('available_from', '<=', $resolvedAt);
                            })
                            ->where(function (Builder $timeQuery) use ($resolvedAt) {
                                $timeQuery->whereNull('available_until')
                                    ->orWhere('available_until', '>', $resolvedAt);
                            });
                    });
            })
            ->where('quest_type', self::TYPE_MAIN);
    }

    public function isCurrentlyVisible(?CarbonInterface $at = null): bool
    {
        $resolvedAt = $at ?? now();

        if ((string) $this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ((string) ($this->schedule_type ?? self::SCHEDULE_MANUAL) === self::SCHEDULE_ONCE) {
            if ($this->available_from && $this->available_from->isFuture()) {
                return false;
            }

            if ($this->available_until && $this->available_until->lessThanOrEqualTo($resolvedAt)) {
                return false;
            }
        }

        return true;
    }

    public function resolveAutomatedStatus(?CarbonInterface $at = null): string
    {
        $resolvedAt = $at ?? now();
        $scheduleType = (string) ($this->schedule_type ?? self::SCHEDULE_MANUAL);

        if ($scheduleType !== self::SCHEDULE_ONCE) {
            return (string) ($this->status ?: self::STATUS_AVAILABLE);
        }

        if ($this->available_from && $resolvedAt->lt($this->available_from)) {
            return self::STATUS_IN_PROGRESS;
        }

        if ($this->available_until && $resolvedAt->gte($this->available_until)) {
            return self::STATUS_DONE;
        }

        return self::STATUS_AVAILABLE;
    }
}
