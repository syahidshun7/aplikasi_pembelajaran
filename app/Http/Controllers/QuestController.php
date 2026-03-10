<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Rubric;
use App\Models\ShopItem;
use App\Models\ShopTransaction;
use App\Models\Submission;
use App\Models\TaskBank;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\StudyGroup;
use App\Models\UserInventory;
use App\Models\UserQuestUnlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola quest.';

    public function userIndex(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $userId = auth()->id();
        $userGroupIds = auth()->user()->studyGroups()->pluck('study_groups.id')->toArray();
        $submittedQuestIds = Submission::where('user_id', $userId)->pluck('quest_id')->toArray();
        $unlockedQuestIds = UserQuestUnlock::query()
            ->where('user_id', $userId)
            ->pluck('quest_id')
            ->toArray();

        $quests = Quest::query()
            ->where(function ($query) use ($userGroupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
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
            ->with('studyGroup:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $quests->through(function ($quest) use ($submittedQuestIds, $unlockedQuestIds) {
            $quest->user_has_submitted = in_array($quest->id, $submittedQuestIds, true);
            $quest->user_has_unlock = in_array($quest->id, $unlockedQuestIds, true);
            return $quest;
        });

        return Inertia::render('Quests/UserIndex', [
            'quests' => $quests,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $adminQuestQuery = Quest::query()
            ->with([
                'studyGroup',
                'taskBank:id,uuid,name,assessment_type,job_role_id',
                'rubric:id,title',
            ]);

        if ($this->isMentorUser()) {
            $mentorJobId = $this->requireMentorJobId();

            $adminQuestQuery->where(function ($query) use ($mentorJobId) {
                $query->whereHas('studyGroup', function ($sq) use ($mentorJobId) {
                    $sq->where('job_id', $mentorJobId);
                })->orWhereHas('taskBank', function ($tq) use ($mentorJobId) {
                    $tq->where('job_role_id', $mentorJobId);
                });
            });
        }

        $studyGroupQuery = StudyGroup::query()->select('id', 'name');
        if ($this->isMentorUser()) {
            $studyGroupQuery->where('job_id', $this->requireMentorJobId());
        }

        $taskBankQuery = TaskBank::query()
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
            'taskBanks' => $taskBankQuery->get(['id', 'uuid', 'name', 'assessment_type']),
            'rubrics' => $rubricsQuery->get(['id', 'title']),
            'filters' => [
                'search' => $search,
            ],
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
            'is_active' => 'nullable|boolean',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'task_bank_id' => 'nullable|exists:task_banks,id',
            'rubric_id' => 'nullable|exists:rubrics,id',
            'deadline' => 'nullable|date', // Tambahkan validasi date
        ]);

        $goldTable = [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ];

        $validated['reward_gold'] = $goldTable[$request->difficulty] ?? 0;
        $validated['reward_exp'] = $goldTable[$request->difficulty] ?? 0;
        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();
        $validated['status'] = $this->resolveQuestStatusFromActiveFlag($validated['is_active'] ?? null);

        $validated['rubric_id'] = $this->resolveQuestRubricId(
            $validated['rubric_id'] ?? null,
            $validated['task_bank_id'] ?? null
        );

        $this->assertMentorCanManageQuestPayload($validated);
        $this->assertMentorCanUseRubricId($validated['rubric_id'] ?? null);

        Quest::create($validated);

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
            'is_active' => 'nullable|boolean',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'task_bank_id' => 'nullable|exists:task_banks,id',
            'rubric_id' => 'nullable|exists:rubrics,id',
            'deadline' => 'nullable|date', // Tambahkan validasi date
        ]);

        $goldTable = [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ];

        // Logika update gold jika difficulty berubah
        $validated['reward_gold'] = $goldTable[$request->difficulty] ?? $validated['reward_gold'];
        $validated['reward_exp'] = $goldTable[$request->difficulty] ?? ($validated['reward_exp'] ?? 0);
        $validated['status'] = $this->resolveQuestStatusFromActiveFlag($validated['is_active'] ?? null);

        $validated['rubric_id'] = $this->resolveQuestRubricId(
            $validated['rubric_id'] ?? null,
            $validated['task_bank_id'] ?? null
        );

        $this->assertMentorCanManageQuestPayload($validated);
        $this->assertMentorCanUseRubricId($validated['rubric_id'] ?? null);

        $quest->update($validated);

        return redirect()->back()->with('message', 'QUEST_CONTRACT_SYNCHRONIZED');
    }

    public function destroy(Quest $quest)
    {
        $this->assertMentorCanManageQuest($quest);
        $quest->delete();

        return redirect()->back()->with('message', 'Mission aborted and removed from board.');
    }


    public function show(Quest $quest)
    {
        $this->authorizeQuestAccessForCurrentUser($quest);

        $userId = (int) auth()->id();
        $quest->load([
            'taskBank:id,uuid,name,assessment_type',
            'taskBank.questions' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->select(['id', 'uuid', 'task_bank_id', 'question_text', 'question_type', 'options_json', 'weight', 'sort_order']);
            },
        ]);

        $submission = $quest->submissions()
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        $isLate = $this->isQuestLate($quest);
        $hasQuestUnlock = UserQuestUnlock::query()
            ->where('user_id', $userId)
            ->where('quest_id', $quest->id)
            ->exists();

        $timeKeyItem = ShopItem::query()
            ->where('code', 'TIME_KEY')
            ->where('is_active', true)
            ->first();

        $timeKeyQty = 0;
        if ($timeKeyItem) {
            $timeKeyQty = (int) UserInventory::query()
                ->where('user_id', $userId)
                ->where('shop_item_id', $timeKeyItem->id)
                ->value('quantity');
        }

        $deadlineActive = $quest->deadline === null || $quest->deadline->isFuture();
        $canFirstSubmit = ! $submission && (! $isLate || $hasQuestUnlock);
        $canResubmitManualPending = (bool) $submission
            && ! $quest->taskBank
            && (string) $submission->status === 'Pending'
            && $deadlineActive;

        $canSubmit = $canFirstSubmit || $canResubmitManualPending;

        return Inertia::render('Quests/Show', [
            'quest' => $quest,
            'hasSubmitted' => !!$submission,
            'existingSubmission' => $submission,
            'isLate' => $isLate,
            'hasQuestUnlock' => $hasQuestUnlock,
            'canSubmit' => $canSubmit,
            'timeKeyQty' => $timeKeyQty,
        ]);
    }

    public function unlockLate(Quest $quest)
    {
        $this->authorizeQuestAccessForCurrentUser($quest);

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

            UserQuestUnlock::query()->create([
                'user_id' => $userId,
                'quest_id' => $quest->id,
                'shop_item_id' => $timeKeyItem->id,
                'unlocked_at' => now(),
            ]);

            $inventory->decrement('quantity', 1);

            ShopTransaction::query()->create([
                'user_id' => $userId,
                'shop_item_id' => $timeKeyItem->id,
                'type' => 'consume_unlock',
                'quantity' => 1,
                'gold_change' => 0,
                'note' => 'Use Time Key to reopen late quest',
                'meta' => [
                    'quest_id' => $quest->id,
                    'quest_uuid' => $quest->uuid,
                    'quest_title' => $quest->title,
                ],
            ]);
        });

        return back()->with('message', 'QUEST_REOPENED_USING_TIME_KEY');
    }

    private function isQuestLate(Quest $quest): bool
    {
        $deadlinePassed = $quest->deadline !== null && $quest->deadline->isPast();
        $statusDone = in_array($quest->status, ['Done', 'Completed'], true);

        return $deadlinePassed || $statusDone;
    }

    private function isMentorUser(): bool
    {
        return (bool) auth()->user()?->isMentor();
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
        $isAllowed = $groupJobId === $mentorJobId || $taskJobId === $mentorJobId;

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
            $isValidGroup = StudyGroup::query()
                ->whereKey($studyGroupId)
                ->where('job_id', $mentorJobId)
                ->exists();

            if (! $isValidGroup) {
                throw ValidationException::withMessages([
                    'study_group_id' => 'Study group tidak sesuai dengan jurusan mentor.',
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

    private function resolveQuestStatusFromActiveFlag($isActive): string
    {
        $active = filter_var($isActive, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $active === false ? 'In-Progress' : 'Available';
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
}
