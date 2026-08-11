<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\JobRole;
use App\Models\Rubric;
use App\Models\ShopItem;
use App\Models\Submission;
use App\Models\TaskBank;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\StudyGroup;
use App\Models\UserInventory;
use App\Models\UserInventoryLog;
use App\Models\UserQuestAttemptUnlock;
use App\Models\UserQuestAttemptSession;
use App\Models\UserQuestUnlock;
use App\Services\StudyGroupStaffAccessService;
use App\Services\QuestAttemptNumberService;
use App\Services\TaskBankExamSessionService;
use App\Support\Cache\CacheVersion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QuestController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola quest.';

    public function userIndex(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'quest_type' => ['nullable', 'in:all,main,optional'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $questType = (string) ($validated['quest_type'] ?? 'all');
        $userId = auth()->id();
        $user = auth()->user();
        $canManageMembership = $user && ! $user->isStaff();
        $userJobId = $user?->job_id;
        $userGroupIds = $canManageMembership
            ? $user->studyGroups()
                ->where('study_groups.job_id', $userJobId)
                ->pluck('study_groups.id')
                ->toArray()
            : [];
        $questsCacheVersion = CacheVersion::get('quests');
        $groupKey = sha1(json_encode(collect($userGroupIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all()));
        $searchKey = $search === '' ? 'none' : sha1($search);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        $cached = Cache::remember(
            "quests.list.v{$questsCacheVersion}.groups.{$groupKey}.type.{$questType}.search.{$searchKey}",
            now()->addMinutes(5),
            function () use ($userGroupIds, $search, $questType) {
                return Quest::query()
                    ->where(function ($query) use ($userGroupIds) {
                        $query->whereNull('study_group_id')
                            ->orWhereIn('study_group_id', $userGroupIds);
                    })
                    ->listedForUsers()
                    ->when($search !== '', function ($query) use ($search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhere('difficulty', 'like', "%{$search}%")
                                ->orWhere('status', 'like', "%{$search}%")
                                ->orWhereHas('studyGroup', function ($sq) use ($search) {
                                    $sq->where('name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->when($questType !== 'all', function ($query) use ($questType) {
                        $query->where('quest_type', $questType);
                    })
                    ->with('studyGroup:id,uuid,name')
                    ->latest()
                    ->get()
                    ->map(fn ($quest) => $quest->toArray())
                    ->values()
                    ->all();
            }
        );

        $questCollection = collect($cached ?? []);

        $questIds = $questCollection
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $submissionStatusesByQuest = [];
        $submittedQuestIdSet = [];
        if (! empty($questIds)) {
            $latestSubmissions = Submission::query()
                ->joinSub(
                    Submission::query()
                        ->where('user_id', $userId)
                        ->whereIn('quest_id', $questIds)
                        ->selectRaw('MAX(id) as id')
                        ->groupBy('quest_id'),
                    'latest',
                    fn ($join) => $join->on('submissions.id', '=', 'latest.id')
                )
                ->get(['submissions.quest_id', 'submissions.status']);

            $submissionStatusesByQuest = $latestSubmissions
                ->pluck('status', 'quest_id')
                ->mapWithKeys(fn ($status, $questId) => [(int) $questId => $status])
                ->all();

            $submittedQuestIdSet = array_fill_keys(array_keys($submissionStatusesByQuest), true);
        }

        $unlockedQuestIdSet = [];
        if (! empty($questIds)) {
            $unlockedQuestIds = UserQuestUnlock::query()
                ->where('user_id', $userId)
                ->whereIn('quest_id', $questIds)
                ->pluck('quest_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $unlockedQuestIdSet = array_fill_keys($unlockedQuestIds, true);
        }

        $sortedQuests = $questCollection
            ->map(function ($quest) use ($submittedQuestIdSet, $submissionStatusesByQuest, $unlockedQuestIdSet) {
                $questId = (int) (is_array($quest) ? ($quest['id'] ?? 0) : ($quest->id ?? 0));

                if (is_array($quest)) {
                    $quest['user_has_submitted'] = isset($submittedQuestIdSet[$questId]);
                    $quest['user_submission_status'] = $submissionStatusesByQuest[$questId] ?? null;
                    $quest['user_has_unlock'] = isset($unlockedQuestIdSet[$questId]);
                    return $quest;
                }

                $quest->user_has_submitted = isset($submittedQuestIdSet[$questId]);
                $quest->user_submission_status = $submissionStatusesByQuest[$questId] ?? null;
                $quest->user_has_unlock = isset($unlockedQuestIdSet[$questId]);
                return $quest;
            })
            ->sortBy(fn ($quest) => $this->questFeedSortTuple($quest))
            ->values();

        $quests = new LengthAwarePaginator(
            $sortedQuests->forPage($page, $perPage)->values()->all(),
            $sortedQuests->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Quests/UserIndex', [
            'quests' => $quests,
            'filters' => [
                'search' => $search,
                'quest_type' => $questType,
            ],
        ]);
    }

    private function questFeedSortTuple(array|object $quest): array
    {
        $status = $this->questValue($quest, 'user_submission_status');
        $priority = match (strtolower(trim((string) $status))) {
            'approved' => 4,
            'pending' => 3,
            'rejected' => 2,
            default => $this->isLateQuestForFeed($quest)
                ? 2
                : ($this->questValue($quest, 'user_has_submitted')
                    ? 3
                    : ($this->hasQuestTimebox($quest) ? 0 : 1)),
        };

        return [
            $priority,
            -$this->questTimestamp($quest, 'deadline'),
            -$this->questTimestamp($quest, 'available_until'),
            -$this->questTimestamp($quest, 'created_at'),
            -((int) $this->questValue($quest, 'id')),
        ];
    }

    private function isLateQuestForFeed(array|object $quest): bool
    {
        if ($this->questValue($quest, 'user_has_submitted') || $this->questValue($quest, 'user_has_unlock')) {
            return false;
        }

        if (trim((string) $this->questValue($quest, 'user_submission_status')) !== '') {
            return false;
        }

        $deadline = $this->questTimestamp($quest, 'deadline');
        if ($deadline > 0 && $deadline <= now()->getTimestamp()) {
            return true;
        }

        return in_array(strtolower(trim((string) $this->questValue($quest, 'status'))), ['done', 'completed'], true);
    }

    private function hasQuestTimebox(array|object $quest): bool
    {
        return (string) $this->questValue($quest, 'schedule_type') === Quest::SCHEDULE_ONCE
            || $this->questTimestamp($quest, 'deadline') > 0;
    }

    private function questTimestamp(array|object $quest, string $key): int
    {
        $value = $this->questValue($quest, $key);

        if (! $value) {
            return 0;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp === false ? 0 : $timestamp;
    }

    private function questValue(array|object $quest, string $key): mixed
    {
        return is_array($quest) ? ($quest[$key] ?? null) : ($quest->{$key} ?? null);
    }

    public function index(Request $request, ?string $groupUuid = null)
    {
        $scopedGroup = $this->resolveScopedStudyGroup($request, $groupUuid);
        $this->abortNonSuperAdminGlobalIndex($request, $scopedGroup);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:active,trash'],
            'quest_type' => ['nullable', 'in:all,main,optional'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $view = (string) ($validated['view'] ?? 'active');
        $questType = (string) ($validated['quest_type'] ?? 'all');

        $adminQuestQuery = Quest::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->when($scopedGroup, fn ($query) => $query->where('study_group_id', (int) $scopedGroup->id))
            ->when($questType !== 'all', fn ($query) => $query->where('quest_type', $questType))
            ->with([
                'studyGroup' => function ($query) {
                    $query->withTrashed();
                },
                'taskBank' => function ($query) {
                    $query->withTrashed()->select('id', 'uuid', 'name', 'assessment_type', 'job_role_id');
                },
                'rubric' => function ($query) {
                    $query->withTrashed()->select('id', 'title');
                },
            ]);

        if ($this->isMentorUser() && ! $scopedGroup) {
            $mentorJobId = $this->requireMentorJobId();

            $adminQuestQuery->where(function ($query) use ($mentorJobId) {
                $query->whereHas('studyGroup', function ($sq) use ($mentorJobId) {
                    $sq->withTrashed();
                    $sq->where('job_id', $mentorJobId);
                })->orWhereHas('taskBank', function ($tq) use ($mentorJobId) {
                    $tq->withTrashed();
                    $tq->where('job_role_id', $mentorJobId);
                });
            });
        }

        $studyGroupQuery = StudyGroup::query()
            ->with('job:id,name')
            ->select('id', 'name', 'job_id');
        if ($scopedGroup) {
            $studyGroupQuery->whereKey((int) $scopedGroup->id);
        }
        if ($this->isMentorUser() && ! $scopedGroup) {
            $studyGroupQuery->where('job_id', $this->requireMentorJobId());
        }

        $taskBankQuery = TaskBank::query()
            ->with('jobRole:id,name')
            ->where('is_active', true)
            ->orderBy('name');
        if ($this->isMentorUser()) {
            $taskBankQuery->where('job_role_id', $this->requireMentorJobId());
        }

        $rubricsQuery = Rubric::query()->orderBy('title');
        if ($this->isMentorUser()) {
            $rubricsQuery->where('mentor_id', (int) $request->user()?->id);
        }

        return Inertia::render('Quests/Index', [
            'quests' => $adminQuestQuery
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('difficulty', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhereHas('studyGroup', function ($sq) use ($search) {
                                $sq->where('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),

            'studyGroups' => $studyGroupQuery->get(),
            'taskBanks' => $taskBankQuery->get(['id', 'uuid', 'name', 'assessment_type', 'job_role_id']),
            'jobRoles' => JobRole::query()
                ->active()
                ->when($this->isMentorUser(), fn ($query) => $query->whereKey($this->requireMentorJobId()))
                ->orderBy('name')
                ->get(['id', 'name']),
            'rubrics' => $rubricsQuery->get(['id', 'title']),
            'filters' => [
                'search' => $search,
                'view' => $view,
                'quest_type' => $questType,
            ],
            'selectedStudyGroup' => $scopedGroup ? [
                'uuid' => (string) $scopedGroup->uuid,
                'id' => (int) $scopedGroup->id,
                'name' => (string) $scopedGroup->name,
                'back_url' => route('groups.detail', $scopedGroup->uuid),
                'quests_url' => route('groups.quests.index', $scopedGroup->uuid),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'difficulty' => 'required|in:C-Rank,B-Rank,A-Rank,S-Rank',
            'reward_gold' => 'nullable|integer|min:0',
            'reward_exp' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'quest_type' => 'nullable|in:main,optional',
            'attempt_mode' => 'nullable|in:single,limited,unlimited',
            'max_attempts' => 'nullable|integer|min:2|max:100|required_if:attempt_mode,limited',
            'grading_attempt' => 'nullable|in:highest,latest,first',
            'is_active' => 'nullable|boolean',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'task_bank_id' => 'nullable|exists:task_banks,id',
            'rubric_id' => 'nullable|exists:rubrics,id',
            'deadline' => 'nullable|date',
            'schedule_type' => 'nullable|in:manual,once',
            'available_from' => 'nullable|date|required_if:schedule_type,once',
            'available_until' => 'nullable|date|after:available_from',
        ]);

        $validated = $this->normalizeQuestSchedulePayload($validated);

        $goldTable = [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ];

        $validated['reward_gold'] = $goldTable[$request->difficulty] ?? 0;
        $validated['reward_exp'] = $goldTable[$request->difficulty] ?? 0;
        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $validated['quest_type'] = (string) ($validated['quest_type'] ?? Quest::TYPE_MAIN);
        $validated['attempt_mode'] = (string) ($validated['attempt_mode'] ?? Quest::ATTEMPT_SINGLE);
        $validated['max_attempts'] = $validated['attempt_mode'] === Quest::ATTEMPT_LIMITED
            ? (int) $validated['max_attempts']
            : null;
        $validated['grading_attempt'] = (string) ($validated['grading_attempt'] ?? Quest::GRADE_HIGHEST);
        $validated['schedule_type'] = (string) ($validated['schedule_type'] ?? Quest::SCHEDULE_MANUAL);
        $validated['status'] = $this->resolveQuestStatusFromPayload($validated);

        $validated['rubric_id'] = $this->resolveQuestRubricId(
            $validated['rubric_id'] ?? null,
            $validated['task_bank_id'] ?? null
        );

        $this->assertMentorCanManageQuestPayload($validated);
        $this->assertMentorCanUseRubricId($validated['rubric_id'] ?? null);

        Quest::create($validated);
        $this->bumpQuestCaches();

        return redirect()->back()->with('message', 'NEW_QUEST_DEPLOYED_SUCCESSFULLY');
    }

    public function update(Request $request, $uuid)
    {
        $quest = Quest::where('uuid', $uuid)->firstOrFail();
        $this->assertMentorCanManageQuest($quest);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'difficulty' => 'required|in:C-Rank,B-Rank,A-Rank,S-Rank',
            'description' => 'nullable|string',
            'reward_gold' => 'required|integer|min:0',
            'reward_exp' => 'nullable|integer|min:0',
            'quest_type' => 'nullable|in:main,optional',
            'attempt_mode' => 'nullable|in:single,limited,unlimited',
            'max_attempts' => 'nullable|integer|min:2|max:100|required_if:attempt_mode,limited',
            'grading_attempt' => 'nullable|in:highest,latest,first',
            'is_active' => 'nullable|boolean',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'task_bank_id' => 'nullable|exists:task_banks,id',
            'rubric_id' => 'nullable|exists:rubrics,id',
            'deadline' => 'nullable|date',
            'schedule_type' => 'nullable|in:manual,once',
            'available_from' => 'nullable|date|required_if:schedule_type,once',
            'available_until' => 'nullable|date|after:available_from',
        ]);

        $validated = $this->normalizeQuestSchedulePayload($validated);

        $goldTable = [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ];

        // Logika update gold jika difficulty berubah
        $validated['reward_gold'] = $goldTable[$request->difficulty] ?? $validated['reward_gold'];
        $validated['reward_exp'] = $goldTable[$request->difficulty] ?? ($validated['reward_exp'] ?? 0);
        $validated['quest_type'] = (string) ($validated['quest_type'] ?? Quest::TYPE_MAIN);
        $validated['attempt_mode'] = (string) ($validated['attempt_mode'] ?? Quest::ATTEMPT_SINGLE);
        $validated['max_attempts'] = $validated['attempt_mode'] === Quest::ATTEMPT_LIMITED
            ? (int) $validated['max_attempts']
            : null;
        $validated['grading_attempt'] = (string) ($validated['grading_attempt'] ?? Quest::GRADE_HIGHEST);
        $validated['schedule_type'] = (string) ($validated['schedule_type'] ?? Quest::SCHEDULE_MANUAL);
        $validated['status'] = $this->resolveQuestStatusFromPayload($validated);

        $validated['rubric_id'] = $this->resolveQuestRubricId(
            $validated['rubric_id'] ?? null,
            $validated['task_bank_id'] ?? null
        );

        $this->assertMentorCanManageQuestPayload($validated);
        $this->assertMentorCanUseRubricId($validated['rubric_id'] ?? null);

        $quest->update($validated);
        $this->bumpQuestCaches();

        return redirect()->back()->with('message', 'QUEST_CONTRACT_SYNCHRONIZED');
    }

    public function destroy(Quest $quest)
    {
        $this->assertMentorCanManageQuest($quest);
        $quest->delete();
        $this->bumpQuestCaches();

        return redirect()->back()->with('message', 'Mission aborted and removed from board.');
    }

    public function restore(string $uuid)
    {
        $quest = Quest::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertMentorCanManageQuest($quest);

        $quest->restore();
        $this->bumpQuestCaches();

        return redirect()->back()->with('message', 'QUEST_RESTORED');
    }

    public function forceDestroy(string $uuid)
    {
        $quest = Quest::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->assertMentorCanManageQuest($quest);

        $filePaths = Submission::withTrashed()
            ->where('quest_id', $quest->id)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->filter(fn ($path) => is_string($path) && $path !== '');

        foreach ($filePaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $quest->forceDelete();
        $this->bumpQuestCaches();

        return redirect()->back()->with('message', 'QUEST_PERMANENTLY_DELETED');
    }


    public function show(Request $request, Quest $quest)
    {
        $this->authorizeQuestAccessForCurrentUser($quest);

        return $this->renderQuestShow($quest, false, $request->query('attempt') === 'new');
    }

    public function userPreview(Request $request, Quest $quest)
    {
        $this->authorizeQuestPreviewAccess($request, $quest);

        return $this->renderQuestShow($quest, true);
    }

    public function previewSubmission(Request $request, Quest $quest)
    {
        $this->authorizeQuestPreviewAccess($request, $quest);

        return back()->with('message', 'QUEST_PREVIEW_SUBMIT_SIMULATED');
    }

    private function renderQuestShow(Quest $quest, bool $previewMode = false, bool $newAttemptRequested = false)
    {
        $userId = (int) auth()->id();
        $quest->load([
            'studyGroup:id,uuid,name',
            'taskBank:id,uuid,name,assessment_type,duration,has_time_limit',
            'taskBank.questions' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->select(['id', 'uuid', 'task_bank_id', 'question_text', 'question_type', 'options_json', 'weight', 'sort_order']);
            },
        ]);

        $submissions = $quest->submissions()
            ->where('user_id', $userId)
            ->latest('id')
            ->get();
        $submission = $submissions->first();
        $attemptCount = $submissions->count();
        $remainingAttempts = $quest->remainingAttempts($attemptCount);
        $nextAttemptNumber = app(QuestAttemptNumberService::class)
            ->nextForSubmission($quest->id, $userId);
        $isLate = $this->isQuestLate($quest);
        $isInactive = (string) ($quest->status ?? '') === 'In-Progress';
        $isStaff = (bool) auth()->user()?->isStaff();
        $isStaffPlayMode = (bool) auth()->user()?->isStaffPlayMode();
        $now = now();
        $isScheduledOnce = (string) ($quest->schedule_type ?? Quest::SCHEDULE_MANUAL) === Quest::SCHEDULE_ONCE;
        $isScheduledHidden = $isScheduledOnce && (
            ($quest->available_from && $now->lt($quest->available_from))
            || ($quest->available_until && $now->gte($quest->available_until))
        );

        if (($isInactive || $isScheduledHidden) && ! $isStaff && ! $submission && ! $isLate) {
            return redirect()
                ->route('quests.user.index')
                ->withErrors(['quest' => $this->questAvailabilityErrorMessage($quest)]);
        }

        $hasQuestUnlock = UserQuestUnlock::query()
            ->where('user_id', $userId)
            ->where('quest_id', $quest->id)
            ->exists();

        $timeKeyItem = ShopItem::query()
            ->where('code', 'TIME_KEY')
            ->where('is_active', true)
            ->first();

        $timeKeyQty = 0;
        if ($timeKeyItem && ! $isStaffPlayMode) {
            $timeKeyQty = (int) UserInventory::query()
                ->where('user_id', $userId)
                ->where('shop_item_id', $timeKeyItem->id)
                ->value('quantity');
        }

        $deadlineActive = $quest->deadline === null || $quest->deadline->isFuture();
        $normalAttemptAvailable = (bool) $submission
            && $deadlineActive
            && in_array((string) $submission->status, [Submission::STATUS_APPROVED, Submission::STATUS_REJECTED], true)
            && $quest->allowsAnotherAttempt($attemptCount, $submission);
        $attemptUnlock = UserQuestAttemptUnlock::query()
            ->where('user_id', $userId)
            ->where('quest_id', $quest->id)
            ->where('attempt_number', $nextAttemptNumber)
            ->whereNull('used_at')
            ->first();
        $canStartNewAttempt = $normalAttemptAvailable || (bool) $attemptUnlock;
        $isNewAttempt = ! $previewMode && $newAttemptRequested && $canStartNewAttempt;
        $displaySubmission = $isNewAttempt ? null : $submission;
        $canFirstSubmit = ! $displaySubmission && (! $isLate || $hasQuestUnlock);
        $canResubmitSubmission = (bool) $displaySubmission
            && (string) $displaySubmission->status === Submission::STATUS_PENDING
            && $deadlineActive;

        $canSubmit = $isNewAttempt || $canFirstSubmit || $canResubmitSubmission;

        $progressAttemptNumber = $displaySubmission
            ? (int) ($displaySubmission->attempt_number ?? 1)
            : $nextAttemptNumber;
        $examSession = null;
        $examSessionService = app(TaskBankExamSessionService::class);
        if (! $previewMode && $canSubmit && $examSessionService->supports($quest)) {
            $examSession = $examSessionService->resolve($quest, $userId, $progressAttemptNumber);
            if ($examSessionService->isExpired($examSession)) {
                $canSubmit = false;
            }
        }
        $previewExamTimer = null;
        if ($previewMode && $examSessionService->supports($quest)) {
            $previewDuration = max(1, (int) ($quest->taskBank?->duration ?? 60));
            $previewStartedAt = now();
            $previewExamTimer = [
                'attempt_number' => 1,
                'duration_minutes' => $previewDuration,
                'started_at' => $previewStartedAt->toISOString(),
                'expires_at' => $previewStartedAt->copy()->addMinutes($previewDuration)->toISOString(),
                'seconds_remaining' => $previewDuration * 60,
                'expired' => false,
                'simulation' => true,
            ];
        }
        $progressKey = "pf-game-state:{$userId}:" . ($quest->uuid ?: $quest->id) . ":{$progressAttemptNumber}";
        $initialProgress = ($previewMode || $isNewAttempt) ? null : Cache::get($progressKey);
        $gradedAttempts = $submissions
            ->filter(fn (Submission $item) => in_array((string) $item->status, [Submission::STATUS_APPROVED, Submission::STATUS_REJECTED], true));
        $retakeTicketItem = ShopItem::query()
            ->where('code', 'RETAKE_TICKET')
            ->where('is_active', true)
            ->first();
        $retakeTicketQty = (! $previewMode && $retakeTicketItem && ! $isStaffPlayMode)
            ? (int) UserInventory::query()
                ->where('user_id', $userId)
                ->where('shop_item_id', $retakeTicketItem->id)
                ->value('quantity')
            : 0;
        $canUseRetakeTicket = ! $previewMode
            && ! $isStaffPlayMode
            && ! $normalAttemptAvailable
            && ! $attemptUnlock
            && in_array((string) ($submission?->status ?? ''), [Submission::STATUS_APPROVED, Submission::STATUS_REJECTED], true);
        $effectiveSubmission = match ((string) ($quest->grading_attempt ?? Quest::GRADE_HIGHEST)) {
            Quest::GRADE_FIRST => $gradedAttempts->sortBy('attempt_number')->first(),
            Quest::GRADE_LATEST => $gradedAttempts->sortByDesc('attempt_number')->first(),
            default => $gradedAttempts->sortByDesc(fn (Submission $item) => (int) ($item->grade ?? 0))->first(),
        };

        return Inertia::render('Quests/Show', [
            'quest' => $quest,
            'hasSubmitted' => $previewMode ? false : !!$displaySubmission,
            'existingSubmission' => $previewMode ? null : $displaySubmission,
            'isLate' => $isLate,
            'hasQuestUnlock' => $hasQuestUnlock,
            'canSubmit' => $previewMode ? true : $canSubmit,
            'timeKeyQty' => $timeKeyQty,
            'isStaffPlayMode' => $isStaffPlayMode,
            'initialPlatformingProgress' => $initialProgress,
            'examTimer' => $examSession ? [
                'attempt_number' => (int) $examSession->attempt_number,
                'duration_minutes' => max(1, (int) ($quest->taskBank?->duration ?? 60)),
                'started_at' => $examSession->started_at?->toISOString(),
                'expires_at' => $examSession->expires_at?->toISOString(),
                'seconds_remaining' => max(0, now()->diffInSeconds($examSession->expires_at, false)),
                'expired' => $examSessionService->isExpired($examSession),
                'simulation' => false,
            ] : $previewExamTimer,
            'examDraft' => $examSession ? [
                'task_answers' => $examSession->draft_answers ?? [],
                'content' => (string) ($examSession->draft_content ?? ''),
                'saved_at' => $examSession->draft_saved_at?->toISOString(),
                'submission_token' => (string) $examSession->submission_token,
            ] : null,
            'attemptContext' => [
                'attempt_count' => (int) $attemptCount,
                'remaining_attempts' => $remainingAttempts,
                'next_attempt_number' => $nextAttemptNumber,
                'is_new_attempt' => $isNewAttempt,
                'can_start_new_attempt' => $canStartNewAttempt,
                'unlocked_by_ticket' => (bool) $attemptUnlock,
                'can_use_retake_ticket' => $canUseRetakeTicket,
                'retake_ticket_quantity' => $retakeTicketQty,
                'attempt_mode' => (string) ($quest->attempt_mode ?? Quest::ATTEMPT_SINGLE),
                'max_attempts' => $quest->max_attempts ? (int) $quest->max_attempts : null,
                'grading_attempt' => (string) ($quest->grading_attempt ?? Quest::GRADE_HIGHEST),
                'effective_grade' => $effectiveSubmission?->grade !== null ? (int) $effectiveSubmission->grade : null,
                'effective_attempt_number' => $effectiveSubmission
                    ? (int) ($effectiveSubmission->attempt_number ?? 1)
                    : null,
                'best_grade' => $gradedAttempts->max('grade'),
                'history' => $submissions
                    ->sortByDesc('attempt_number')
                    ->map(fn (Submission $item) => [
                        'uuid' => (string) $item->uuid,
                        'attempt_number' => (int) ($item->attempt_number ?? 1),
                        'status' => (string) $item->status,
                        'grade' => $item->grade !== null ? (int) $item->grade : null,
                        'earned_exp' => (int) ($item->earned_exp ?? 0),
                        'earned_gold' => (int) ($item->earned_gold ?? 0),
                        'is_effective' => $effectiveSubmission
                            && (int) $item->id === (int) $effectiveSubmission->id,
                        'submitted_at' => $item->created_at?->toISOString(),
                    ])
                    ->values(),
            ],
            'previewMode' => $previewMode,
            'previewSubmitUrl' => $previewMode ? route('quests.user-preview.submissions', $quest->uuid) : null,
            'backUrl' => $previewMode && $quest->studyGroup
                ? route('groups.quests.index', $quest->studyGroup->uuid)
                : null,
        ]);
    }

    private function authorizeQuestPreviewAccess(Request $request, Quest $quest): void
    {
        $user = $request->user();

        abort_unless($user?->isStaff(), 403, 'QUEST_PREVIEW_STAFF_ONLY');

        $quest->loadMissing('studyGroup');

        if (! $quest->studyGroup) {
            abort_unless($user->isAdmin(), 403, 'GLOBAL_QUEST_PREVIEW_ADMIN_ONLY');
            return;
        }

        abort_unless(
            app(StudyGroupStaffAccessService::class)->canAccess($user, $quest->studyGroup),
            403,
            'QUEST_PREVIEW_STUDY_GROUP_ACCESS_DENIED'
        );
    }

    public function savePlatformingProgress(Request $request, Quest $quest)
    {
        $userId = auth()->id();
        $data = $request->validate([
            'index' => 'required|integer',
            'level' => 'required|integer',
            'answers' => 'required|array',
            'time_left' => 'required|integer',
            'wm_state' => 'nullable|array', // Tambahan untuk word_match
            'attempt_number' => 'required|integer|min:1',
            'state_version' => 'nullable|integer|min:1',
        ]);

        $progressKey = "pf-game-state:{$userId}:"
            . ($quest->uuid ?: $quest->id)
            . ':' . (int) $data['attempt_number'];
        Cache::put($progressKey, $data, now()->addHours(2));

        return response()->json(['status' => 'success']);
    }

    public function loadPlatformingProgress(Request $request, Quest $quest)
    {
        $userId = auth()->id();
        $attemptNumber = max(1, (int) $request->integer('attempt_number', 1));
        $progressKey = "pf-game-state:{$userId}:"
            . ($quest->uuid ?: $quest->id)
            . ":{$attemptNumber}";
        return response()->json(Cache::get($progressKey));
    }

    public function unlockLate(Quest $quest)
    {
        $this->authorizeQuestAccessForCurrentUser($quest);

        if ((bool) auth()->user()?->isStaffPlayMode()) {
            throw ValidationException::withMessages([
                'unlock' => 'Staff play mode tidak bisa memakai Time Key atau membuka ulang quest.',
            ]);
        }

        $userId = (int) auth()->id();

        $alreadySubmitted = Submission::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadySubmitted) {
            return back()->with('message', 'SUBMISSION_ALREADY_EXISTS_NO_UNLOCK_NEEDED');
        }

        if (! $this->isQuestLate($quest)) {
            return back()->withErrors([
                'unlock' => 'Quest ini belum melewati deadline.',
            ]);
        }

        $existingUnlock = UserQuestUnlock::query()
            ->where('user_id', $userId)
            ->where('quest_id', $quest->id)
            ->exists();

        if ($existingUnlock) {
            return back()->with('message', 'QUEST_ALREADY_REOPENED');
        }

        $timeKeyItem = ShopItem::query()
            ->where('code', 'TIME_KEY')
            ->where('is_active', true)
            ->first();

        if (! $timeKeyItem) {
            throw ValidationException::withMessages([
                'unlock' => 'Item Time Key belum tersedia di shop.',
            ]);
        }

        DB::transaction(function () use ($userId, $quest, $timeKeyItem) {
            $inventory = UserInventory::query()
                ->where('user_id', $userId)
                ->where('shop_item_id', $timeKeyItem->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || (int) $inventory->quantity < 1) {
                throw ValidationException::withMessages([
                    'unlock' => 'Kamu tidak punya Time Key. Beli dulu di shop.',
                ]);
            }

            $unlock = UserQuestUnlock::query()->create([
                'user_id' => $userId,
                'quest_id' => $quest->id,
                'shop_item_id' => $timeKeyItem->id,
                'unlocked_at' => now(),
            ]);

            UserQuestAttemptSession::query()
                ->where('user_id', $userId)
                ->where('quest_id', $quest->id)
                ->whereNull('submitted_at')
                ->delete();

            $quantityBefore = (int) ($inventory->quantity ?? 0);
            $quantityAfter = max(0, $quantityBefore - 1);

            $inventory->decrement('quantity', 1);

            UserInventoryLog::query()->create([
                'user_id' => $userId,
                'shop_item_id' => $timeKeyItem->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'quantity_change' => -1,
                'type' => UserInventoryLog::TYPE_USE,
                'reference_type' => UserQuestUnlock::class,
                'reference_id' => (int) $unlock->id,
                'note' => 'Use Time Key to reopen late quest',
                'meta' => [
                    'quest_id' => $quest->id,
                    'quest_uuid' => $quest->uuid,
                    'quest_title' => $quest->title,
                ],
            ]);
        });

        CacheVersion::bump('home');
        CacheVersion::bump('quests');
        CacheVersion::bump('shop');

        return back()->with('message', 'QUEST_REOPENED_USING_TIME_KEY');
    }

    public function unlockRetake(Quest $quest)
    {
        $this->authorizeQuestAccessForCurrentUser($quest);

        if ((bool) auth()->user()?->isStaffPlayMode()) {
            throw ValidationException::withMessages([
                'retake' => 'Staff play mode tidak bisa memakai Retake Ticket.',
            ]);
        }

        $userId = (int) auth()->id();
        $retakeTicket = ShopItem::query()
            ->where('code', 'RETAKE_TICKET')
            ->where('is_active', true)
            ->first();

        if (! $retakeTicket) {
            throw ValidationException::withMessages([
                'retake' => 'Retake Ticket belum tersedia di shop.',
            ]);
        }

        DB::transaction(function () use ($quest, $userId, $retakeTicket) {
            $latest = Submission::query()
                ->where('quest_id', $quest->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            $historicalSubmissions = Submission::withTrashed()
                ->where('quest_id', $quest->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
            $attemptCount = Submission::query()
                ->where('quest_id', $quest->id)
                ->where('user_id', $userId)
                ->count();
            $unusedUnlock = UserQuestAttemptUnlock::query()
                ->where('user_id', $userId)
                ->where('quest_id', $quest->id)
                ->whereNull('used_at')
                ->lockForUpdate()
                ->orderBy('attempt_number')
                ->first();

            if ($unusedUnlock) {
                return;
            }

            $unlockMax = (int) UserQuestAttemptUnlock::query()
                ->where('user_id', $userId)
                ->where('quest_id', $quest->id)
                ->lockForUpdate()
                ->max('attempt_number');
            $nextAttemptNumber = max(
                (int) $historicalSubmissions->max('attempt_number'),
                $unlockMax,
            ) + 1;

            if (! $latest || ! in_array((string) $latest->status, [Submission::STATUS_APPROVED, Submission::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'retake' => 'Retake Ticket hanya dapat dipakai setelah quest berstatus Approved atau Rejected.',
                ]);
            }

            if ($this->isDeadlineActive($quest) && $quest->allowsAnotherAttempt($attemptCount, $latest)) {
                throw ValidationException::withMessages([
                    'retake' => 'Quest ini masih memiliki attempt normal. Retake Ticket belum diperlukan.',
                ]);
            }

            $inventory = UserInventory::query()
                ->where('user_id', $userId)
                ->where('shop_item_id', $retakeTicket->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || (int) $inventory->quantity < 1) {
                throw ValidationException::withMessages([
                    'retake' => 'Kamu tidak punya Retake Ticket. Beli dulu di shop.',
                ]);
            }

            $unlock = UserQuestAttemptUnlock::query()->create([
                'user_id' => $userId,
                'quest_id' => $quest->id,
                'shop_item_id' => $retakeTicket->id,
                'attempt_number' => $nextAttemptNumber,
                'unlocked_at' => now(),
            ]);
            $quantityBefore = (int) $inventory->quantity;
            $inventory->decrement('quantity');

            UserInventoryLog::query()->create([
                'user_id' => $userId,
                'shop_item_id' => $retakeTicket->id,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityBefore - 1,
                'quantity_change' => -1,
                'type' => UserInventoryLog::TYPE_USE,
                'reference_type' => UserQuestAttemptUnlock::class,
                'reference_id' => (int) $unlock->id,
                'note' => 'Use Retake Ticket for an extra quest attempt',
                'meta' => [
                    'quest_id' => $quest->id,
                    'quest_uuid' => $quest->uuid,
                    'quest_title' => $quest->title,
                    'attempt_number' => $nextAttemptNumber,
                ],
            ]);
        });

        CacheVersion::bump('quests');
        CacheVersion::bump('shop');

        return back()->with('message', 'RETAKE_TICKET_ACTIVATED');
    }

    private function isQuestLate(Quest $quest): bool
    {
        $deadlinePassed = $quest->deadline !== null && $quest->deadline->isPast();
        $isScheduledOnce = (string) ($quest->schedule_type ?? Quest::SCHEDULE_MANUAL) === Quest::SCHEDULE_ONCE;
        $statusDone = $isScheduledOnce && in_array((string) $quest->status, ['Done', 'Completed'], true);

        return $deadlinePassed || $statusDone;
    }

    private function isDeadlineActive(Quest $quest): bool
    {
        return $quest->deadline === null || $quest->deadline->isFuture();
    }

    private function isMentorUser(): bool
    {
        return (bool) auth()->user()?->isMentor();
    }

    private function resolveScopedStudyGroup(Request $request, ?string $groupUuid): ?StudyGroup
    {
        $groupUuid = trim((string) ($groupUuid ?? ''));
        if ($groupUuid === '') {
            return null;
        }

        $group = StudyGroup::query()->where('uuid', $groupUuid)->firstOrFail();
        abort_unless(
            app(StudyGroupStaffAccessService::class)->canAccess($request->user(), $group),
            403,
            'STUDY_GROUP_STAFF_ACCESS_DENIED'
        );

        return $group;
    }

    private function abortNonSuperAdminGlobalIndex(Request $request, ?StudyGroup $scopedGroup): void
    {
        if ($scopedGroup) {
            return;
        }

        abort_unless(
            (string) ($request->user()?->role ?? '') === \App\Models\User::ROLE_SUPER_ADMIN,
            403,
            'SUPER_ADMIN_ONLY_GLOBAL_QUEST_INDEX'
        );
    }

    private function requireMentorJobId(): int
    {
        $jobId = (int) (auth()->user()?->job_id ?? 0);
        abort_if($jobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);
        return $jobId;
    }

    private function assertMentorCanManageQuest(Quest $quest): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        $quest->loadMissing([
            'studyGroup:id,job_id',
            'taskBank:id,job_role_id',
        ]);

        $groupJobId = (int) ($quest->studyGroup?->job_id ?? 0);
        $taskJobId = (int) ($quest->taskBank?->job_role_id ?? 0);
        $isAllowed = ($quest->studyGroup && app(StudyGroupStaffAccessService::class)->canAccess(auth()->user(), $quest->studyGroup))
            || $taskJobId === $mentorJobId;

        abort_unless($isAllowed, 403, 'MENTOR_CANNOT_MANAGE_QUEST_OUTSIDE_JOB');
    }

    private function assertMentorCanManageQuestPayload(array $payload): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        $studyGroupId = (int) ($payload['study_group_id'] ?? 0);
        $taskBankId = (int) ($payload['task_bank_id'] ?? 0);

        if ($studyGroupId <= 0 && $taskBankId <= 0) {
            throw ValidationException::withMessages([
                'study_group_id' => 'Mentor wajib mengaitkan quest ke study group atau task bank jurusannya.',
            ]);
        }

        if ($studyGroupId > 0) {
            $group = StudyGroup::query()->find($studyGroupId);
            $isValidGroup = $group && app(StudyGroupStaffAccessService::class)->canAccess(auth()->user(), $group);

            if (! $isValidGroup) {
                throw ValidationException::withMessages([
                    'study_group_id' => 'Mentor tidak punya akses ke study group ini.',
                ]);
            }
        }

        if ($taskBankId > 0) {
            $isValidTaskBank = TaskBank::query()
                ->whereKey($taskBankId)
                ->where('job_role_id', $mentorJobId)
                ->exists();

            if (! $isValidTaskBank) {
                throw ValidationException::withMessages([
                    'task_bank_id' => 'Task bank tidak sesuai dengan jurusan mentor.',
                ]);
            }
        }
    }

    private function resolveQuestRubricId($explicitRubricId, $taskBankId): ?int
    {
        $taskBankId = (int) ($taskBankId ?? 0);
        if ($taskBankId > 0) {
            // Rubric tidak boleh dipakai untuk quest yang sumbernya dari question bank (task bank).
            return null;
        }

        $explicitRubricId = (int) ($explicitRubricId ?? 0);
        if ($explicitRubricId > 0) {
            return $explicitRubricId;
        }

        return null;
    }

    private function resolveQuestStatusFromPayload(array $payload): string
    {
        $scheduleType = (string) ($payload['schedule_type'] ?? Quest::SCHEDULE_MANUAL);

        if ($scheduleType === Quest::SCHEDULE_ONCE) {
            $quest = new Quest([
                'schedule_type' => $scheduleType,
                'available_from' => $payload['available_from'] ?? null,
                'available_until' => $payload['available_until'] ?? null,
            ]);

            return $quest->resolveAutomatedStatus(now());
        }

        $active = filter_var($payload['is_active'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $active === false
            ? Quest::STATUS_IN_PROGRESS
            : Quest::STATUS_AVAILABLE;
    }

    private function normalizeQuestSchedulePayload(array $payload): array
    {
        $payload['schedule_type'] = (string) ($payload['schedule_type'] ?? Quest::SCHEDULE_MANUAL);

        if ($payload['schedule_type'] !== Quest::SCHEDULE_ONCE) {
            $payload['available_from'] = null;
            $payload['available_until'] = null;
        }

        return $payload;
    }

    private function bumpQuestCaches(): void
    {
        CacheVersion::bump('quests');
        CacheVersion::bump('home');
    }

    private function assertMentorCanUseRubricId($rubricId): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $rubricId = (int) ($rubricId ?? 0);
        if ($rubricId <= 0) {
            return;
        }

        $mentorId = (int) auth()->id();
        $isOwned = Rubric::query()
            ->whereKey($rubricId)
            ->where('mentor_id', $mentorId)
            ->exists();

        abort_unless($isOwned, 403, 'MENTOR_CANNOT_USE_RUBRIC_NOT_OWNED');
    }

    private function authorizeQuestAccessForCurrentUser(Quest $quest): void
    {
        if (! $quest->study_group_id) {
            return;
        }

        abort_if((bool) auth()->user()?->isStaffPlayMode(), 403, 'STAFF_PLAY_MODE_QUEST_ACCESS_DENIED');

        $userGroupIds = auth()->user()
            ->studyGroups()
            ->pluck('study_groups.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(
            in_array((int) $quest->study_group_id, $userGroupIds, true),
            403,
            'QUEST_ACCESS_DENIED'
        );
    }

    private function questAvailabilityErrorMessage(Quest $quest): string
    {
        $now = now();
        $isScheduledOnce = (string) ($quest->schedule_type ?? Quest::SCHEDULE_MANUAL) === Quest::SCHEDULE_ONCE;

        if ($isScheduledOnce && $quest->available_from && $now->lt($quest->available_from)) {
            return 'QUEST_NOT_YET_AVAILABLE';
        }

        if ($isScheduledOnce && $quest->available_until && $now->gte($quest->available_until)) {
            return 'QUEST_SCHEDULE_WINDOW_CLOSED';
        }

        return 'QUEST_INACTIVE';
    }
}
