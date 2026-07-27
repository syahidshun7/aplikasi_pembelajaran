<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Models\JobRole;
use App\Models\DailyQuest;
use App\Models\ShopTransaction;
use App\Models\UserGoldAdjustment;
use App\Models\UserGoldTransfer;
use App\Services\LevelingService;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(array_merge(['all'], User::assignableRoles()))],
            'rank_by' => ['nullable', 'in:newest,highest_gold,highest_exp,highest_grade'],
            'grade_order' => ['nullable', 'in:none,asc,desc'],
            'view' => ['nullable', 'in:active,trash'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $role = (string) ($validated['role'] ?? 'all');
        $rankBy = (string) ($validated['rank_by'] ?? 'newest');
        $gradeOrder = (string) ($validated['grade_order'] ?? 'none');
        $view = (string) ($validated['view'] ?? 'active');
        if ($rankBy === 'highest_grade' && $gradeOrder === 'none') {
            // Backward compatibility for old query params.
            $gradeOrder = 'desc';
        }

        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : 'level';

        $users = User::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->with([
                'detailUser:id,user_id,bio,experience,location,skills',
                'job:id,name',
            ])
            ->withCount('submissions')
            ->withMax('submissions as highest_grade', 'grade')
            ->when($role !== '' && $role !== 'all', function ($query) use ($role) {
                $query->where('role', $role);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($gradeOrder === 'desc', function ($query) {
                $query->orderByRaw('COALESCE(highest_grade, 0) DESC')
                    ->orderByDesc('exp')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'asc', function ($query) {
                $query->orderByRaw('COALESCE(highest_grade, 0) ASC')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'none' && $rankBy === 'highest_gold', function ($query) {
                $query->orderByDesc('gold')
                    ->orderByDesc('exp')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'none' && $rankBy === 'highest_exp', function ($query) {
                $query->orderByDesc('exp')
                    ->orderByDesc('gold')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'none' && $rankBy === 'newest', function ($query) {
                $query->orderByDesc('created_at');
            })
            ->paginate(10, [
                'id',
                'name',
                'username',
                'email',
                'role',
                'job_id',
                'profile_photo',
                'gold',
                'exp',
                $levelColumn,
                'created_at',
                'deleted_at',
            ])
            ->withQueryString();

        $pageUsers = $users->getCollection();
        $userIds = $pageUsers->pluck('id')->all();

        if (!empty($userIds)) {
            $allQuests = Quest::query()
                ->publishedForAverage()
                ->get(['id', 'study_group_id']);

            $publicQuestIds = [];
            $groupQuestIdsByGroup = [];
            foreach ($allQuests as $quest) {
                if (is_null($quest->study_group_id)) {
                    $publicQuestIds[] = (int) $quest->id;
                } else {
                    $groupId = (int) $quest->study_group_id;
                    $groupQuestIdsByGroup[$groupId][] = (int) $quest->id;
                }
            }

            $userGroupIdsMap = DB::table('group_user')
                ->whereIn('user_id', $userIds)
                ->whereNull('deleted_at')
                ->select('user_id', 'study_group_id')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->pluck('study_group_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

            $latestSubmissions = Submission::query()
                ->whereIn('user_id', $userIds)
                ->orderByDesc('id')
                ->get(['user_id', 'quest_id', 'grade']);

            $latestGradeByUserQuest = [];
            foreach ($latestSubmissions as $submission) {
                $uid = (int) $submission->user_id;
                $qid = (int) $submission->quest_id;

                if (!isset($latestGradeByUserQuest[$uid][$qid])) {
                    $latestGradeByUserQuest[$uid][$qid] = (int) ($submission->grade ?? 0);
                }
            }

            $pageUsers->transform(function ($user) use ($publicQuestIds, $groupQuestIdsByGroup, $userGroupIdsMap, $latestGradeByUserQuest) {
                $uid = (int) $user->id;

                $availableQuestIds = $publicQuestIds;
                $userGroupIds = $userGroupIdsMap->get($uid, []);
                foreach ($userGroupIds as $groupId) {
                    if (isset($groupQuestIdsByGroup[$groupId])) {
                        $availableQuestIds = array_merge($availableQuestIds, $groupQuestIdsByGroup[$groupId]);
                    }
                }

                $availableQuestIds = array_values(array_unique($availableQuestIds));
                $totalAvailableQuests = count($availableQuestIds);

                $gradeSum = 0;
                $userLatestGrades = $latestGradeByUserQuest[$uid] ?? [];
                foreach ($availableQuestIds as $questId) {
                    $gradeSum += (int) ($userLatestGrades[$questId] ?? 0);
                }

                $user->avg_grade = $totalAvailableQuests > 0
                    ? round($gradeSum / $totalAvailableQuests, 1)
                    : 0;
                $user->level_display = LevelingService::levelFromExp((int) ($user->exp ?? 0));
                $user->highest_grade = (int) ($user->highest_grade ?? 0);

                return $user;
            });

            $users->setCollection($pageUsers);
        }

        return Inertia::render('Users/Admin/Index', [
            'users' => $users,
            'availableRoles' => User::assignableRoles(),
            'jobRoles' => JobRole::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'rank_by' => $rankBy,
                'grade_order' => $gradeOrder,
                'view' => $view,
            ],
        ]);
    }

    public function ledger(Request $request, User $user): Response
    {
        $sourceOptions = $this->ledgerSourceOptions();
        $sourceKeys = array_keys($sourceOptions);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(array_merge(['all'], $sourceKeys))],
            'direction' => ['nullable', Rule::in(['all', 'income', 'expense', 'neutral'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $source = (string) ($validated['source'] ?? 'all');
        $direction = (string) ($validated['direction'] ?? 'all');
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 25);

        $records = $this->buildUserGoldLedgerRecords($user);

        if ($source !== 'all') {
            $records = $records->where('source_key', $source)->values();
        }

        if ($direction !== 'all') {
            $records = $records->where('direction', $direction)->values();
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $records = $records->filter(function (array $row) use ($needle) {
                $haystacks = [
                    (string) ($row['source_label'] ?? ''),
                    (string) ($row['note'] ?? ''),
                    (string) ($row['reference'] ?? ''),
                    (string) ($row['item_name'] ?? ''),
                    (string) ($row['item_code'] ?? ''),
                ];

                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && str_contains(mb_strtolower($haystack), $needle)) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        $fromTs = $dateFrom ? Carbon::parse($dateFrom)->startOfDay()->timestamp : null;
        $toTs = $dateTo ? Carbon::parse($dateTo)->endOfDay()->timestamp : null;

        if ($fromTs !== null) {
            $records = $records->filter(fn (array $row) => (int) ($row['occurred_at_ts'] ?? 0) >= $fromTs)->values();
        }

        if ($toTs !== null) {
            $records = $records->filter(fn (array $row) => (int) ($row['occurred_at_ts'] ?? 0) <= $toTs)->values();
        }

        $records = $records
            ->sortByDesc('occurred_at_ts')
            ->values();

        $summary = [
            'income_total' => (int) $records->sum(fn (array $row) => max(0, (int) ($row['gold_change'] ?? 0))),
            'expense_total' => (int) $records->sum(fn (array $row) => abs(min(0, (int) ($row['gold_change'] ?? 0)))),
            'net_total' => (int) $records->sum(fn (array $row) => (int) ($row['gold_change'] ?? 0)),
            'transaction_count' => (int) $records->count(),
            'current_gold' => (int) ($user->gold ?? 0),
        ];

        $sourceBreakdown = collect($sourceOptions)
            ->map(function (string $label, string $key) use ($records) {
                $rows = $records->where('source_key', $key);

                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => (int) $rows->count(),
                    'net_total' => (int) $rows->sum(fn (array $row) => (int) ($row['gold_change'] ?? 0)),
                ];
            })
            ->values()
            ->all();

        $page = max(1, (int) $request->query('page', 1));
        $total = (int) $records->count();
        $items = $records
            ->forPage($page, $perPage)
            ->values()
            ->map(function (array $row) {
                unset($row['occurred_at_ts']);
                return $row;
            })
            ->all();

        $ledger = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Users/Admin/Ledger', [
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?? ''),
                'username' => (string) ($user->username ?? ''),
                'email' => (string) ($user->email ?? ''),
                'role' => (string) ($user->role ?? ''),
                'gold' => (int) ($user->gold ?? 0),
                'exp' => (int) ($user->exp ?? 0),
            ],
            'ledger' => $ledger,
            'summary' => $summary,
            'sourceBreakdown' => $sourceBreakdown,
            'sourceOptions' => collect($sourceOptions)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'source' => $source,
                'direction' => $direction,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function adjustGold(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! Schema::hasTable('user_gold_adjustments')) {
            return back()->withErrors([
                'amount' => 'Tabel audit gold adjustment belum tersedia. Jalankan migration terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $validated) {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $beforeGold = (int) ($lockedUser->gold ?? 0);
            $amount = (int) $validated['amount'];
            $goldChange = $validated['direction'] === 'add' ? $amount : -$amount;
            $afterGold = max(0, $beforeGold + $goldChange);
            $actualGoldChange = $afterGold - $beforeGold;

            if ($actualGoldChange === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Gold user sudah 0, tidak ada yang bisa dikurangi.',
                ]);
            }

            $lockedUser->forceFill([
                'gold' => $afterGold,
            ])->save();

            UserGoldAdjustment::query()->create([
                'user_id' => (int) $lockedUser->id,
                'admin_user_id' => (int) $request->user()->id,
                'gold_before' => $beforeGold,
                'gold_after' => $afterGold,
                'gold_change' => $actualGoldChange,
                'reason' => trim((string) ($validated['reason'] ?? '')) ?: 'Admin ledger gold adjustment',
                'meta' => [
                    'context' => 'admin.users.ledger.adjust_gold',
                    'requested_direction' => (string) $validated['direction'],
                    'requested_amount' => $amount,
                    'admin_role' => (string) ($request->user()->role ?? ''),
                ],
            ]);
        });

        return back()->with('message', 'USER_GOLD_ADJUSTED');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(User::assignableRoles())],
        ]);

        // Prevent an admin from accidentally removing their own admin access.
        if ((int) $request->user()->id === (int) $user->id && ! in_array($validated['role'], User::adminRoles(), true)) {
            return back()->withErrors([
                'role' => 'Kamu tidak bisa menurunkan role akun admin yang sedang login.',
            ]);
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return back()->with('message', 'USER_ROLE_UPDATED');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return back()->with('message', 'USER_PASSWORD_RESET_SUCCESS');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $previousJobId = $user->job_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(User::assignableRoles())],
            'job_id' => ['nullable', 'integer', 'exists:job_roles,id'],
            'exp' => ['required', 'integer', 'min:0'],
            'level' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'bio' => ['nullable', 'string', 'max:1200'],
            'experience' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'skills_text' => ['nullable', 'string', 'max:500'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        if ((int) $request->user()->id === (int) $user->id && ! in_array($validated['role'], User::adminRoles(), true)) {
            return back()->withErrors([
                'role' => 'Kamu tidak bisa menurunkan role akun admin yang sedang login.',
            ]);
        }

        $payload = [
            'name' => trim((string) $validated['name']),
            'username' => trim((string) ($validated['username'] ?? '')) ?: null,
            'email' => strtolower(trim((string) $validated['email'])),
            'role' => $validated['role'],
            'job_id' => $validated['job_id'] ?? null,
            'exp' => (int) $validated['exp'],
        ];

        $calculatedLevel = LevelingService::levelFromExp((int) $payload['exp']);

        if (Schema::hasColumn('users', 'lvl')) {
            $payload['lvl'] = $calculatedLevel;
        }
        if (Schema::hasColumn('users', 'level')) {
            $payload['level'] = $calculatedLevel;
        }
        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make((string) $validated['password']);
        }

        $user->forceFill($payload)->save();

        $avatarUpdated = false;
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $path = $request->file('profile_photo')->store('profiles', 'public');
            $user->profile_photo = $path;
            $user->save();
            $avatarUpdated = true;
        } elseif (!empty($validated['remove_avatar'])) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $user->profile_photo = null;
            $user->save();
            $avatarUpdated = true;
        }

        $bio = isset($validated['bio']) ? trim((string) $validated['bio']) : null;
        $experience = isset($validated['experience']) ? trim((string) $validated['experience']) : null;
        $location = isset($validated['location']) ? trim((string) $validated['location']) : null;
        $skillsRaw = isset($validated['skills_text']) ? trim((string) $validated['skills_text']) : '';

        $bio = $bio !== '' ? $bio : null;
        $experience = $experience !== '' ? $experience : null;
        $location = $location !== '' ? $location : null;

        $skills = [];
        if ($skillsRaw !== '') {
            $skills = collect(explode(',', $skillsRaw))
                ->map(fn ($skill) => trim((string) $skill))
                ->filter(fn ($skill) => $skill !== '')
                ->unique()
                ->values()
                ->all();
        }

        $detailPayload = [
            'bio' => $bio,
            'experience' => $experience,
            'location' => $location,
            'skills' => !empty($skills) ? $skills : null,
        ];

        $hasDetailValues = collect($detailPayload)->contains(function ($value) {
            if (is_array($value)) {
                return count($value) > 0;
            }

            return !is_null($value) && $value !== '';
        });

        if ($hasDetailValues) {
            $user->detailUser()->updateOrCreate(['user_id' => $user->id], $detailPayload);
        } elseif ($user->detailUser) {
            $user->detailUser()->delete();
        }

        $jobChanged = (int) ($previousJobId ?? 0) !== (int) ($user->job_id ?? 0);

        if ($validated['role'] === User::ROLE_MENTOR || $user->detailUser || $hasDetailValues || $avatarUpdated || $jobChanged) {
            CacheVersion::bump('landing');
        }

        return back()->with('message', 'USER_DATA_UPDATED');
    }

    private function ledgerSourceOptions(): array
    {
        return [
            'submission_reward' => 'Submission Reward',
            'daily_quest_claim' => 'Daily Quest Claim',
            'shop_purchase' => 'Shop Purchase',
            'shop_consume_unlock' => 'Use Time Key',
            'shop_refund' => 'Shop Refund',
            'admin_adjustment' => 'Admin Gold Adjustment',
            'user_transfer' => 'User Gold Transfer',
        ];
    }

    private function buildUserGoldLedgerRecords(User $user): Collection
    {
        return collect()
            ->concat($this->buildSubmissionLedgerRecords($user))
            ->concat($this->buildDailyQuestLedgerRecords($user))
            ->concat($this->buildShopLedgerRecords($user))
            ->concat($this->buildAdminAdjustmentLedgerRecords($user))
            ->concat($this->buildTransferLedgerRecords($user))
            ->values();
    }

    private function buildSubmissionLedgerRecords(User $user): Collection
    {
        $bestGoldByQuest = [];

        return Submission::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('status', ['Approved', 'Rejected'])
            ->where('reward_eligible', true)
            ->with('quest:id,uuid,title')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'uuid', 'quest_id', 'status', 'grade', 'earned_gold', 'created_at'])
            ->map(function (Submission $submission) use (&$bestGoldByQuest) {
                $questId = (int) $submission->quest_id;
                $previousBest = (int) ($bestGoldByQuest[$questId] ?? 0);
                $attemptReward = max(0, (int) ($submission->earned_gold ?? 0));
                $goldChange = max(0, $attemptReward - $previousBest);
                $bestGoldByQuest[$questId] = max($previousBest, $attemptReward);
                $occurredAt = $submission->created_at ?? now();

                return [
                    'id' => 'submission:' . (int) $submission->id,
                    'source_key' => 'submission_reward',
                    'source_label' => 'Submission Reward',
                    'direction' => $this->directionFromGoldChange($goldChange),
                    'gold_change' => $goldChange,
                    'amount' => abs($goldChange),
                    'note' => trim(sprintf(
                        'Quest: %s | Status: %s | Grade: %d%% | Best reward increase',
                        (string) ($submission->quest?->title ?? 'Unknown Quest'),
                        (string) ($submission->status ?? '-'),
                        (int) ($submission->grade ?? 0),
                    )),
                    'reference' => (string) ($submission->uuid ?? ''),
                    'item_name' => (string) ($submission->quest?->title ?? ''),
                    'item_code' => (string) ($submission->quest?->uuid ?? ''),
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_at_ts' => (int) $occurredAt->timestamp,
                ];
            })
            ->filter(fn (array $record) => (int) $record['gold_change'] > 0)
            ->values();
    }

    private function buildDailyQuestLedgerRecords(User $user): Collection
    {
        return DailyQuest::query()
            ->where('user_id', (int) $user->id)
            ->where('status', DailyQuest::STATUS_CLAIMED)
            ->where('reward_gold', '!=', 0)
            ->get(['id', 'uuid', 'title', 'activity_type', 'reward_gold', 'claimed_at', 'created_at'])
            ->map(function (DailyQuest $dailyQuest) {
                $goldChange = (int) ($dailyQuest->reward_gold ?? 0);
                $occurredAt = $dailyQuest->claimed_at ?? $dailyQuest->created_at ?? now();

                return [
                    'id' => 'daily_quest:' . (int) $dailyQuest->id,
                    'source_key' => 'daily_quest_claim',
                    'source_label' => 'Daily Quest Claim',
                    'direction' => $this->directionFromGoldChange($goldChange),
                    'gold_change' => $goldChange,
                    'amount' => abs($goldChange),
                    'note' => trim(sprintf(
                        '%s | Activity: %s',
                        (string) ($dailyQuest->title ?? 'Daily Quest'),
                        (string) ($dailyQuest->activity_type ?? '-')
                    )),
                    'reference' => (string) ($dailyQuest->uuid ?? ''),
                    'item_name' => (string) ($dailyQuest->title ?? ''),
                    'item_code' => (string) ($dailyQuest->activity_type ?? ''),
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_at_ts' => (int) $occurredAt->timestamp,
                ];
            })
            ->values();
    }

    private function buildShopLedgerRecords(User $user): Collection
    {
        $rows = collect();

        $transactions = ShopTransaction::query()
            ->where('user_id', (int) $user->id)
            ->with('item:id,name,code')
            ->get(['id', 'shop_item_id', 'type', 'quantity', 'gold_change', 'note', 'meta', 'created_at']);

        foreach ($transactions as $transaction) {
            $type = (string) ($transaction->type ?? '');
            $meta = is_array($transaction->meta) ? $transaction->meta : [];

            $normalizedGoldChange = match ($type) {
                'purchase' => -abs((int) ($transaction->gold_change ?? 0)),
                'consume_unlock' => 0,
                default => (int) ($transaction->gold_change ?? 0),
            };

            $sourceKey = match ($type) {
                'purchase' => 'shop_purchase',
                'consume_unlock' => 'shop_consume_unlock',
                default => 'shop_purchase',
            };

            $sourceLabel = match ($type) {
                'purchase' => 'Shop Purchase',
                'consume_unlock' => 'Use Time Key',
                default => 'Shop Transaction',
            };

            $occurredAt = $transaction->created_at ?? now();

            $rows->push([
                'id' => 'shop:' . (int) $transaction->id,
                'source_key' => $sourceKey,
                'source_label' => $sourceLabel,
                'direction' => $this->directionFromGoldChange($normalizedGoldChange),
                'gold_change' => $normalizedGoldChange,
                'amount' => abs($normalizedGoldChange),
                'note' => (string) ($transaction->note ?? ''),
                'reference' => (string) ((int) $transaction->id),
                'item_name' => (string) ($transaction->item?->name ?? ''),
                'item_code' => (string) ($transaction->item?->code ?? ''),
                'occurred_at' => $occurredAt->toIso8601String(),
                'occurred_at_ts' => (int) $occurredAt->timestamp,
            ]);

            $refundGold = max(0, (int) ($meta['refund_gold'] ?? 0));
            $cancelledAtRaw = $meta['admin_cancelled_at'] ?? null;

            if ($refundGold > 0 && is_string($cancelledAtRaw) && trim($cancelledAtRaw) !== '') {
                $cancelledAt = Carbon::parse($cancelledAtRaw);
                $rows->push([
                    'id' => 'shop_refund:' . (int) $transaction->id,
                    'source_key' => 'shop_refund',
                    'source_label' => 'Shop Refund',
                    'direction' => 'income',
                    'gold_change' => $refundGold,
                    'amount' => $refundGold,
                    'note' => 'Admin cancelled purchase and refunded gold',
                    'reference' => (string) ((int) $transaction->id),
                    'item_name' => (string) ($transaction->item?->name ?? ''),
                    'item_code' => (string) ($transaction->item?->code ?? ''),
                    'occurred_at' => $cancelledAt->toIso8601String(),
                    'occurred_at_ts' => (int) $cancelledAt->timestamp,
                ]);
            }
        }

        return $rows->values();
    }

    private function buildAdminAdjustmentLedgerRecords(User $user): Collection
    {
        if (! Schema::hasTable('user_gold_adjustments')) {
            return collect();
        }

        return UserGoldAdjustment::query()
            ->where('user_id', (int) $user->id)
            ->with('admin:id,name,username')
            ->get(['id', 'admin_user_id', 'gold_before', 'gold_after', 'gold_change', 'reason', 'meta', 'created_at'])
            ->map(function (UserGoldAdjustment $adjustment) {
                $goldChange = (int) ($adjustment->gold_change ?? 0);
                $occurredAt = $adjustment->created_at ?? now();

                return [
                    'id' => 'admin_adjustment:' . (int) $adjustment->id,
                    'source_key' => 'admin_adjustment',
                    'source_label' => 'Admin Gold Adjustment',
                    'direction' => $this->directionFromGoldChange($goldChange),
                    'gold_change' => $goldChange,
                    'amount' => abs($goldChange),
                    'note' => trim(sprintf(
                        '%s | by: %s',
                        (string) ($adjustment->reason ?? 'Manual adjustment'),
                        (string) ($adjustment->admin?->username ?? $adjustment->admin?->name ?? 'unknown')
                    )),
                    'reference' => (string) ((int) $adjustment->id),
                    'item_name' => '',
                    'item_code' => '',
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_at_ts' => (int) $occurredAt->timestamp,
                ];
            })
            ->values();
    }

    private function buildTransferLedgerRecords(User $user): Collection
    {
        if (! Schema::hasTable('user_gold_transfers')) {
            return collect();
        }

        return UserGoldTransfer::query()
            ->where('status', UserGoldTransfer::STATUS_COMPLETED)
            ->where(function ($query) use ($user) {
                $query->where('sender_id', (int) $user->id)
                    ->orWhere('recipient_id', (int) $user->id);
            })
            ->with(['sender:id,name,username', 'recipient:id,name,username'])
            ->get(['id', 'sender_id', 'recipient_id', 'amount', 'status', 'note', 'created_at'])
            ->map(function (UserGoldTransfer $transfer) use ($user) {
                $isSender = (int) $transfer->sender_id === (int) $user->id;
                $amount = (int) ($transfer->amount ?? 0);
                $goldChange = $isSender ? -$amount : $amount;
                $occurredAt = $transfer->created_at ?? now();
                $counterparty = $isSender ? $transfer->recipient : $transfer->sender;
                $counterpartyName = (string) ($counterparty?->username ?? $counterparty?->name ?? 'unknown');

                return [
                    'id' => 'user_transfer:' . (int) $transfer->id . ':' . ($isSender ? 'out' : 'in'),
                    'source_key' => 'user_transfer',
                    'source_label' => 'User Gold Transfer',
                    'direction' => $this->directionFromGoldChange($goldChange),
                    'gold_change' => $goldChange,
                    'amount' => abs($goldChange),
                    'note' => trim(sprintf(
                        '%s %s | %s',
                        $isSender ? 'Transfer to' : 'Transfer from',
                        $counterpartyName,
                        (string) ($transfer->note ?? '-')
                    )),
                    'reference' => (string) ((int) $transfer->id),
                    'item_name' => $counterpartyName,
                    'item_code' => $isSender ? 'OUT' : 'IN',
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_at_ts' => (int) $occurredAt->timestamp,
                ];
            })
            ->values();
    }

    private function directionFromGoldChange(int $goldChange): string
    {
        if ($goldChange > 0) {
            return 'income';
        }

        if ($goldChange < 0) {
            return 'expense';
        }

        return 'neutral';
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors([
                'user' => 'Kamu tidak bisa menghapus akun admin yang sedang login.',
            ]);
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();
        CacheVersion::bump('home');
        CacheVersion::bump('dashboard');
        CacheVersion::bump('landing');

        return back()->with('message', 'USER_ACCOUNT_DELETED');
    }

    public function restore(int $userId): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();

        CacheVersion::bump('home');
        CacheVersion::bump('dashboard');
        CacheVersion::bump('landing');

        return back()->with('message', 'USER_ACCOUNT_RESTORED');
    }

    public function forceDestroy(Request $request, int $userId): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);

        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors([
                'user' => 'Kamu tidak bisa menghapus permanen akun admin yang sedang login.',
            ]);
        }

        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->notifications()->delete();
        $user->forceDelete();

        CacheVersion::bump('home');
        CacheVersion::bump('dashboard');
        CacheVersion::bump('landing');

        return back()->with('message', 'USER_ACCOUNT_PERMANENTLY_DELETED');
    }
}
