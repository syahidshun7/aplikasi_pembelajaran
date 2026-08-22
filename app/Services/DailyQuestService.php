<?php

namespace App\Services;

use App\Models\DailyQuest;
use App\Models\DailyQuestDefinition;
use App\Models\Event;
use App\Models\User;
use App\Notifications\DailyQuestCompletedNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DailyQuestService
{
    public function buildBoardForUser(User $user, ?CarbonInterface $now = null): ?array
    {
        if (! $this->supportsUser($user)) {
            return null;
        }

        $resolvedNow = $this->resolveNow($now);
        $todayQuests = $this->recordLoginPresence($user, $resolvedNow);

        $lifetimeClaimedTotals = DailyQuest::query()
            ->where('user_id', (int) $user->id)
            ->where('status', DailyQuest::STATUS_CLAIMED)
            ->selectRaw('COALESCE(SUM(reward_exp), 0) as total_exp, COALESCE(SUM(reward_gold), 0) as total_gold')
            ->first();

        $todayClaimedExp = (int) $todayQuests
            ->where('status', DailyQuest::STATUS_CLAIMED)
            ->sum('reward_exp');

        $todayClaimedGold = (int) $todayQuests
            ->where('status', DailyQuest::STATUS_CLAIMED)
            ->sum('reward_gold');

        return [
            'items' => $todayQuests
                ->map(function (DailyQuest $dailyQuest) {
                    $target = max(1, (int) ($dailyQuest->target_value ?? 1));
                    $progress = max(0, min($target, (int) ($dailyQuest->progress_value ?? 0)));
                    $status = (string) ($dailyQuest->status ?? DailyQuest::STATUS_PENDING);

                    return [
                        'uuid' => (string) $dailyQuest->uuid,
                        'title' => (string) $dailyQuest->title,
                        'description' => (string) ($dailyQuest->description ?? ''),
                        'activity_type' => (string) $dailyQuest->activity_type,
                        'progress' => $progress,
                        'target' => $target,
                        'progress_percent' => (int) round(($progress / $target) * 100),
                        'reward_exp' => (int) ($dailyQuest->reward_exp ?? 0),
                        'reward_gold' => (int) ($dailyQuest->reward_gold ?? 0),
                        'status' => $status,
                        'activity_steps' => $this->activityStepsForQuest($dailyQuest),
                        'is_claimable' => $status === DailyQuest::STATUS_COMPLETED,
                        'completed_at' => $dailyQuest->completed_at?->toIso8601String(),
                        'claimed_at' => $dailyQuest->claimed_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'summary' => [
                'total' => (int) $todayQuests->count(),
                'completed' => (int) $todayQuests->whereIn('status', [
                    DailyQuest::STATUS_COMPLETED,
                    DailyQuest::STATUS_CLAIMED,
                ])->count(),
                'claimed' => (int) $todayQuests->where('status', DailyQuest::STATUS_CLAIMED)->count(),
                'claimable' => (int) $todayQuests->where('status', DailyQuest::STATUS_COMPLETED)->count(),
                'bonus_exp_total' => (int) ($lifetimeClaimedTotals->total_exp ?? 0),
                'bonus_gold_total' => (int) ($lifetimeClaimedTotals->total_gold ?? 0),
                'today_claimed_exp' => $todayClaimedExp,
                'today_claimed_gold' => $todayClaimedGold,
            ],
            'server_now' => $resolvedNow->toIso8601String(),
            'next_reset_at' => $this->nextResetAt($resolvedNow)->toIso8601String(),
            'timezone' => $this->timezone(),
        ];
    }

    public function ensureDailyQuestsForUser(User $user, ?CarbonInterface $now = null): Collection
    {
        if (! $this->supportsUser($user)) {
            return collect();
        }

        $resolvedNow = $this->resolveNow($now);
        $questDate = $resolvedNow->toDateString();
        $expiresAt = $this->nextResetAt($resolvedNow);
        $existingDefinitionIds = DailyQuest::query()
            ->where('user_id', (int) $user->id)
            ->whereDate('quest_date', $questDate)
            ->pluck('daily_quest_definition_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $definitions = DailyQuestDefinition::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (DailyQuestDefinition $definition) => $this->definitionIsAvailableForUser($definition, $user, $resolvedNow))
            ->values();

        foreach ($definitions as $definition) {
            if (in_array((int) $definition->id, $existingDefinitionIds, true)) {
                continue;
            }

            try {
                DailyQuest::query()->create([
                    'daily_quest_definition_id' => (int) $definition->id,
                    'user_id' => (int) $user->id,
                    'quest_date' => $questDate,
                    'title' => (string) $definition->title,
                    'description' => (string) ($definition->description ?? ''),
                    'activity_type' => (string) $definition->activity_type,
                    'target_value' => (int) ($definition->target_value ?? 1),
                    'progress_value' => 0,
                    'reward_exp' => (int) ($definition->reward_exp ?? 0),
                    'reward_gold' => (int) ($definition->reward_gold ?? 0),
                    'sort_order' => (int) ($definition->sort_order ?? 1),
                    'status' => DailyQuest::STATUS_PENDING,
                    'expires_at' => $expiresAt,
                    'meta' => [
                        'definition_code' => (string) $definition->code,
                        'definition_meta' => $definition->meta ?? [],
                    ],
                ]);
            } catch (QueryException $exception) {
                if (! str_contains((string) $exception->getMessage(), 'daily_quests.user_id')) {
                    throw $exception;
                }
            }

            $existingDefinitionIds[] = (int) $definition->id;
        }

        return DailyQuest::query()
            ->where('user_id', (int) $user->id)
            ->whereDate('quest_date', $questDate)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function generateDailyQuestsForAll(?CarbonInterface $now = null): int
    {
        $resolvedNow = $this->resolveNow($now);
        $createdCount = 0;

        User::query()
            ->whereNotIn('role', User::staffRoles())
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$createdCount, $resolvedNow) {
                foreach ($users as $user) {
                    $questDate = $resolvedNow->toDateString();
                    $before = DailyQuest::query()
                        ->where('user_id', (int) $user->id)
                        ->whereDate('quest_date', $questDate)
                        ->count();

                    $after = $this->ensureDailyQuestsForUser($user, $resolvedNow)->count();
                    $createdCount += max(0, $after - $before);
                }
            });

        return $createdCount;
    }

    public function quickStatusForUser(User $user, ?CarbonInterface $now = null): ?array
    {
        if (! $this->supportsUser($user)) {
            return null;
        }

        $resolvedNow = $this->resolveNow($now);
        $todayQuests = $this->recordLoginPresence($user, $resolvedNow);

        return [
            'available' => true,
            'total' => (int) $todayQuests->count(),
            'completed' => (int) $todayQuests->whereIn('status', [
                DailyQuest::STATUS_COMPLETED,
                DailyQuest::STATUS_CLAIMED,
            ])->count(),
            'claimable' => (int) $todayQuests->where('status', DailyQuest::STATUS_COMPLETED)->count(),
            'claimed' => (int) $todayQuests->where('status', DailyQuest::STATUS_CLAIMED)->count(),
            'next_reset_at' => $this->nextResetAt($resolvedNow)->toIso8601String(),
        ];
    }

    public function recordActivity(
        User|int $user,
        string $activityType,
        int $amount = 1,
        array $context = [],
        ?CarbonInterface $occurredAt = null,
    ): array {
        if ($amount <= 0) {
            return $this->emptyActivityFeedback();
        }

        $resolvedUser = $user instanceof User
            ? $user
            : User::query()->find((int) $user);

        if (! $resolvedUser || ! $this->supportsUser($resolvedUser)) {
            return $this->emptyActivityFeedback();
        }

        $resolvedAt = $this->resolveNow($occurredAt);
        $this->ensureDailyQuestsForUser($resolvedUser, $resolvedAt);

        $feedback = DB::transaction(function () use ($resolvedUser, $activityType, $amount, $context, $resolvedAt) {
            $dailyQuests = DailyQuest::query()
                ->where('user_id', (int) $resolvedUser->id)
                ->whereDate('quest_date', $resolvedAt->toDateString())
                ->where('activity_type', $activityType)
                ->whereIn('status', [
                    DailyQuest::STATUS_PENDING,
                    DailyQuest::STATUS_COMPLETED,
                ])
                ->lockForUpdate()
                ->get();

            $updatedQuests = [];
            $completedQuests = [];

            foreach ($dailyQuests as $dailyQuest) {
                $target = max(1, (int) ($dailyQuest->target_value ?? 1));
                $currentProgress = max(0, (int) ($dailyQuest->progress_value ?? 0));
                $nextProgress = min($target, $currentProgress + $amount);

                $meta = is_array($dailyQuest->meta) ? $dailyQuest->meta : [];
                $meta['last_activity_type'] = $activityType;
                $meta['last_activity_at'] = $resolvedAt->toIso8601String();
                if ($context !== []) {
                    $meta['last_context'] = $context;
                }

                $payload = [
                    'progress_value' => $nextProgress,
                    'meta' => $meta,
                ];

                if (
                    $nextProgress >= $target
                    && (string) $dailyQuest->status === DailyQuest::STATUS_PENDING
                ) {
                    $payload['status'] = DailyQuest::STATUS_COMPLETED;
                    $payload['completed_at'] = $resolvedAt;
                }

                $dailyQuest->fill($payload);

                if ($dailyQuest->isDirty()) {
                    $dailyQuest->save();

                    $questFeedback = $this->feedbackPayloadForQuest($dailyQuest);
                    $updatedQuests[] = $questFeedback;

                    if (($payload['status'] ?? null) === DailyQuest::STATUS_COMPLETED) {
                        $completedQuests[] = $questFeedback;
                    }
                }
            }

            return [
                'updated_quests' => $updatedQuests,
                'completed_quests' => $completedQuests,
                'claimable_count' => DailyQuest::query()
                    ->where('user_id', (int) $resolvedUser->id)
                    ->whereDate('quest_date', $resolvedAt->toDateString())
                    ->where('status', DailyQuest::STATUS_COMPLETED)
                    ->count(),
            ];
        });

        foreach ($feedback['completed_quests'] as $completedQuest) {
            $quest = DailyQuest::query()->find((int) ($completedQuest['id'] ?? 0));
            if ($quest) {
                $resolvedUser->notify(new DailyQuestCompletedNotification($quest));
            }
        }

        return $feedback;
    }

    public function recordLoginPresence(User $user, ?CarbonInterface $now = null): Collection
    {
        if (! $this->supportsUser($user)) {
            return collect();
        }

        $resolvedNow = $this->resolveNow($now);
        $todayQuests = $this->ensureDailyQuestsForUser($user, $resolvedNow);

        $hasPendingLoginQuest = $todayQuests->contains(function (DailyQuest $dailyQuest) {
            return (string) $dailyQuest->activity_type === DailyQuestDefinition::ACTIVITY_LOGIN
                && (string) $dailyQuest->status === DailyQuest::STATUS_PENDING;
        });

        if (! $hasPendingLoginQuest) {
            return $todayQuests;
        }

        $this->recordActivity($user, DailyQuestDefinition::ACTIVITY_LOGIN, 1, [
            'source' => 'active_session_presence',
        ], $resolvedNow);

        return DailyQuest::query()
            ->where('user_id', (int) $user->id)
            ->whereDate('quest_date', $resolvedNow->toDateString())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function claim(DailyQuest $dailyQuest, User $user, ?CarbonInterface $now = null): DailyQuest
    {
        $resolvedNow = $this->resolveNow($now);

        return DB::transaction(function () use ($dailyQuest, $user, $resolvedNow) {
            /** @var DailyQuest $lockedQuest */
            $lockedQuest = DailyQuest::query()
                ->whereKey($dailyQuest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedQuest->user_id !== (int) $user->id) {
                abort(403);
            }

            if ($lockedQuest->expires_at && $lockedQuest->expires_at->lessThanOrEqualTo($resolvedNow)) {
                if ((string) $lockedQuest->status !== DailyQuest::STATUS_CLAIMED) {
                    $lockedQuest->update([
                        'status' => DailyQuest::STATUS_EXPIRED,
                    ]);
                }

                throw ValidationException::withMessages([
                    'daily_quest' => 'Daily quest ini sudah expired dan tidak bisa diklaim lagi.',
                ]);
            }

            if ((string) $lockedQuest->status === DailyQuest::STATUS_CLAIMED) {
                throw ValidationException::withMessages([
                    'daily_quest' => 'Reward daily quest ini sudah pernah diklaim.',
                ]);
            }

            $target = max(1, (int) ($lockedQuest->target_value ?? 1));
            $progress = max(0, (int) ($lockedQuest->progress_value ?? 0));

            if ($progress < $target) {
                throw ValidationException::withMessages([
                    'daily_quest' => 'Progress quest belum memenuhi syarat klaim reward.',
                ]);
            }

            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rewardExp = max(0, (int) ($lockedQuest->reward_exp ?? 0));
            $rewardGold = max(0, (int) ($lockedQuest->reward_gold ?? 0));

            $lockedQuest->update([
                'status' => DailyQuest::STATUS_CLAIMED,
                'completed_at' => $lockedQuest->completed_at ?? $resolvedNow,
                'claimed_at' => $resolvedNow,
            ]);

            $nextExp = max(0, (int) ($lockedUser->exp ?? 0) + $rewardExp);
            $nextGold = max(0, (int) ($lockedUser->gold ?? 0) + $rewardGold);

            $updateData = [
                'exp' => $nextExp,
                'gold' => $nextGold,
            ];

            $nextLevel = LevelingService::levelFromExp($nextExp);
            if (Schema::hasColumn('users', 'lvl')) {
                $updateData['lvl'] = $nextLevel;
            } elseif (Schema::hasColumn('users', 'level')) {
                $updateData['level'] = $nextLevel;
            }

            User::query()
                ->whereKey($lockedUser->id)
                ->update($updateData);

            return $lockedQuest->fresh();
        });
    }

    public function expireStaleQuests(?CarbonInterface $now = null): int
    {
        $resolvedNow = $this->resolveNow($now);

        return DailyQuest::query()
            ->whereIn('status', [
                DailyQuest::STATUS_PENDING,
                DailyQuest::STATUS_COMPLETED,
            ])
            ->where('expires_at', '<=', $resolvedNow)
            ->update([
                'status' => DailyQuest::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    public function nextResetAt(?CarbonInterface $now = null): CarbonImmutable
    {
        $resolvedNow = $this->resolveNow($now);

        return $resolvedNow->startOfDay()->addDay();
    }

    private function supportsUser(User $user): bool
    {
        return ! $user->isStaff();
    }

    private function definitionIsAvailableForUser(DailyQuestDefinition $definition, User $user, CarbonImmutable $now): bool
    {
        if ((string) $definition->activity_type !== DailyQuestDefinition::ACTIVITY_EVENT_ATTENDANCE) {
            return true;
        }

        return $this->userHasAccessibleSelfAttendanceEvent($user, $now);
    }

    private function userHasAccessibleSelfAttendanceEvent(User $user, CarbonImmutable $now): bool
    {
        $userGroupIds = $user->studyGroups()
            ->pluck('study_groups.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $userJobId = (int) ($user->job_id ?? 0);

        return Event::query()
            ->where('self_attendance_enabled', true)
            ->where(function ($query) use ($userGroupIds, $userJobId) {
                $query->where(function ($publicQuery) use ($userJobId) {
                    $publicQuery->whereNull('study_group_id')
                        ->where(function ($audienceQuery) use ($userJobId) {
                            $audienceQuery->whereNull('job_id');

                            if ($userJobId > 0) {
                                $audienceQuery->orWhere('job_id', $userJobId);
                            }
                        });
                });

                if (! empty($userGroupIds)) {
                    $query->orWhereIn('study_group_id', $userGroupIds);
                }
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now->startOfDay());
            })
            ->exists();
    }

    private function resolveNow(?CarbonInterface $now = null): CarbonImmutable
    {
        if ($now instanceof CarbonImmutable) {
            return $now->setTimezone($this->timezone());
        }

        if ($now instanceof CarbonInterface) {
            return CarbonImmutable::instance($now)->setTimezone($this->timezone());
        }

        return CarbonImmutable::now($this->timezone());
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    private function feedbackPayloadForQuest(DailyQuest $dailyQuest): array
    {
        $target = max(1, (int) ($dailyQuest->target_value ?? 1));
        $progress = max(0, min($target, (int) ($dailyQuest->progress_value ?? 0)));

        return [
            'id' => (int) $dailyQuest->id,
            'uuid' => (string) $dailyQuest->uuid,
            'title' => (string) $dailyQuest->title,
            'status' => (string) $dailyQuest->status,
            'progress' => $progress,
            'target' => $target,
            'reward_exp' => (int) ($dailyQuest->reward_exp ?? 0),
            'reward_gold' => (int) ($dailyQuest->reward_gold ?? 0),
        ];
    }

    private function activityStepsForQuest(DailyQuest $dailyQuest): array
    {
        $meta = is_array($dailyQuest->meta) ? $dailyQuest->meta : [];
        $definitionMeta = is_array($meta['definition_meta'] ?? null) ? $meta['definition_meta'] : [];
        $configuredSteps = collect($definitionMeta['activity_steps'] ?? [])
            ->filter(fn ($step) => is_string($step) && trim($step) !== '')
            ->map(fn (string $step) => trim($step))
            ->values()
            ->all();

        if ($configuredSteps !== []) {
            return $configuredSteps;
        }

        return match ((string) $dailyQuest->activity_type) {
            DailyQuestDefinition::ACTIVITY_LOGIN => [
                'Login ke akunmu hari ini.',
                'Buka dashboard sampai progress daily quest tercatat.',
                'Kalau status sudah complete, claim reward dari card ini.',
            ],
            DailyQuestDefinition::ACTIVITY_QUEST_SUBMISSION => [
                'Buka quest atau task yang tersedia untukmu.',
                'Kirim submission baru dari halaman quest.',
                'Setelah submit pertama hari ini berhasil, reward bisa langsung di-claim.',
            ],
            DailyQuestDefinition::ACTIVITY_EVENT_ATTENDANCE => [
                'Cari event hari ini yang membuka self attendance.',
                'Masuk ke detail event lalu lakukan check-in attendance.',
                'Saat attendance tercatat, reward akan siap di-claim.',
            ],
            default => [
                'Selesaikan aktivitas yang tertulis pada quest ini.',
                'Pastikan progress mencapai target hari ini.',
                'Claim reward setelah status quest complete.',
            ],
        };
    }

    private function emptyActivityFeedback(): array
    {
        return [
            'updated_quests' => [],
            'completed_quests' => [],
            'claimable_count' => 0,
        ];
    }
}
