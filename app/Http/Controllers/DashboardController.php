<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\Event;
use App\Models\ErrorLog;
use App\Models\JobRole;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\User;
use App\Services\LevelingService;
use App\Services\StudyGroupStaffAccessService;
use App\Support\Cache\CacheVersion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(StudyGroupStaffAccessService $studyGroupAccess)
    {
        $authUser = auth()->user();
        $isMentor = (bool) $authUser?->isMentor();
        $isSuperAdmin = (string) ($authUser?->role ?? '') === User::ROLE_SUPER_ADMIN;
        $mentorJobId = (int) ($authUser?->job_id ?? 0);

        if ($isMentor && $mentorJobId <= 0) {
            abort(403, 'MENTOR_JOB_REQUIRED');
        }

        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : 'level';

        $guideQuery = Guide::query();
        $questQuery = Quest::query();
        $eventQuery = Event::query();
        $studyGroupQuery = StudyGroup::query();
        $studentQuery = User::query()->whereNotIn('role', User::staffRoles());
        $pendingSubmissionQuery = Submission::query()->where('status', 'Pending');
        $gradedSubmissionQuery = Submission::query()->whereIn('status', ['Approved', 'Rejected']);

        if ($isMentor) {
            $guideQuery->whereHas('studyGroup', function ($q) use ($mentorJobId) {
                $q->where('job_id', $mentorJobId);
            });

            $questQuery->whereHas('studyGroup', function ($q) use ($mentorJobId) {
                $q->where('job_id', $mentorJobId);
            });

            $eventQuery->whereHas('studyGroup', function ($q) use ($mentorJobId) {
                $q->where('job_id', $mentorJobId);
            });

            $studyGroupQuery->where('job_id', $mentorJobId);
            $studentQuery->where('job_id', $mentorJobId);

            $pendingSubmissionQuery->whereHas('quest', function ($q) use ($mentorJobId) {
                $q->where(function ($w) use ($mentorJobId) {
                    $w->whereHas('studyGroup', function ($sq) use ($mentorJobId) {
                        $sq->where('job_id', $mentorJobId);
                    })->orWhereHas('taskBank', function ($tq) use ($mentorJobId) {
                        $tq->where('job_role_id', $mentorJobId);
                    });
                });
            });

            $gradedSubmissionQuery->whereHas('quest', function ($q) use ($mentorJobId) {
                $q->where(function ($w) use ($mentorJobId) {
                    $w->whereHas('studyGroup', function ($sq) use ($mentorJobId) {
                        $sq->where('job_id', $mentorJobId);
                    })->orWhereHas('taskBank', function ($tq) use ($mentorJobId) {
                        $tq->where('job_role_id', $mentorJobId);
                    });
                });
            });
        }

        $dashboardCacheVersion = CacheVersion::get('dashboard');
        $scopeKey = $isMentor ? ('mentor_job.' . $mentorJobId) : 'global';

        $stats = Cache::remember(
            "dashboard.stats.content-v3.v{$dashboardCacheVersion}.{$scopeKey}",
            now()->addMinutes(3),
            function () use ($guideQuery, $studyGroupQuery, $studentQuery, $pendingSubmissionQuery, $gradedSubmissionQuery, $isMentor, $isSuperAdmin) {
                $avgGrade30d = $isSuperAdmin
                    ? 0.0
                    : (float) (clone $gradedSubmissionQuery)
                        ->where('created_at', '>=', now()->subDays(30))
                        ->avg('grade');
                $graded7dCount = $isSuperAdmin
                    ? 0
                    : (int) (clone $gradedSubmissionQuery)
                        ->where('created_at', '>=', now()->subDays(7))
                        ->count();
                $errors24h = $isSuperAdmin
                    ? (int) ErrorLog::query()->where('created_at', '>=', now()->subDay())->count()
                    : 0;

                return [
                    'total_materi' => $isSuperAdmin ? 0 : (int) $guideQuery->count(),
                    'total_study_groups' => (int) $studyGroupQuery->count(),
                    'total_jobs' => $isMentor ? 1 : (int) JobRole::query()->count(),
                    'total_students' => (int) $studentQuery->count(),
                    'pending_verdicts' => (int) $pendingSubmissionQuery->count(),
                    'avg_grade_30d' => round($avgGrade30d, 1),
                    'graded_7d' => $graded7dCount,
                    'system_errors_24h' => $errors24h,
                    'system_health' => $errors24h === 0
                        ? 'healthy'
                        : ($errors24h < 5 ? 'warning' : 'critical'),
                ];
            }
        );

        $page = LengthAwarePaginator::resolveCurrentPage();
        $studentsCachePayload = $isSuperAdmin ? [
            'items' => [],
            'total' => 0,
            'per_page' => 10,
        ] : Cache::remember(
            "dashboard.students.v{$dashboardCacheVersion}.{$scopeKey}.page.{$page}",
            now()->addMinutes(3),
            function () use ($isMentor, $mentorJobId, $levelColumn, $page) {
                $studentsQuery = User::query()
                    ->whereNotIn('role', User::staffRoles());

                if ($isMentor) {
                    $studentsQuery->where('job_id', $mentorJobId);
                }

                // NOTE: Sort + perhitungan avg_grade harus konsisten dengan "grade average" di sisi user/profile:
                // avg_grade = (sum latest grade per available quest) / (total available quests),
                // di mana quest yang belum disubmit tetap masuk denominator (nilai 0).
                $allStudents = $studentsQuery->get(['id', 'name', 'username', 'exp', $levelColumn]);
                $userIds = $allStudents->pluck('id')->map(fn ($id) => (int) $id)->all();

                if (empty($userIds)) {
                    return [
                        'items' => [],
                        'total' => 0,
                        'per_page' => 10,
                    ];
                }

                $publicQuestCount = (int) Quest::query()
                    ->whereNull('study_group_id')
                    ->publishedForAverage()
                    ->count();

                $groupQuestCountByGroupId = Quest::query()
                    ->whereNotNull('study_group_id')
                    ->publishedForAverage()
                    ->selectRaw('study_group_id, COUNT(*) as cnt')
                    ->groupBy('study_group_id')
                    ->pluck('cnt', 'study_group_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $groupNamesByGroupId = DB::table('study_groups')
                    ->whereIn('id', array_keys($groupQuestCountByGroupId))
                    ->pluck('name', 'id')
                    ->map(fn ($name) => (string) $name)
                    ->all();

                $userGroupIdsMap = DB::table('group_user')
                    ->whereIn('user_id', $userIds)
                    ->whereNull('deleted_at')
                    ->select('user_id', 'study_group_id')
                    ->distinct()
                    ->get()
                    ->groupBy('user_id')
                    ->map(fn ($rows) => $rows->pluck('study_group_id')->map(fn ($id) => (int) $id)->values()->all())
                    ->all();

                $totalCompletedByUser = Submission::query()
                    ->whereIn('user_id', $userIds)
                    ->whereIn('status', ['Approved', 'Rejected'])
                    ->selectRaw('user_id, COUNT(*) as cnt')
                    ->groupBy('user_id')
                    ->pluck('cnt', 'user_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $latestGradesByUser = [];
                $latestSubmissions = Submission::query()
                    ->joinSub(
                        Submission::query()
                            ->whereIn('user_id', $userIds)
                            ->selectRaw('MAX(id) as id')
                            ->groupBy('user_id', 'quest_id'),
                        'latest',
                        fn ($join) => $join->on('submissions.id', '=', 'latest.id')
                    )
                    ->leftJoin('quests', 'quests.id', '=', 'submissions.quest_id')
                    ->get(['submissions.user_id', 'submissions.grade', 'submissions.status', 'quests.study_group_id']);

                foreach ($latestSubmissions as $submission) {
                    $uid = (int) $submission->user_id;
                    $latestGradesByUser[$uid][] = [
                        'study_group_id' => is_null($submission->study_group_id) ? null : (int) $submission->study_group_id,
                        'grade' => (int) ($submission->grade ?? 0),
                        'status' => (string) ($submission->status ?? ''),
                    ];
                }

                $allStudents->transform(function ($user) use ($levelColumn, $publicQuestCount, $groupQuestCountByGroupId, $groupNamesByGroupId, $userGroupIdsMap, $totalCompletedByUser, $latestGradesByUser) {
                    $uid = (int) $user->id;

                    $groupIds = $userGroupIdsMap[$uid] ?? [];
                    $groupIdSet = [];
                    foreach ($groupIds as $gid) {
                        $groupIdSet[(int) $gid] = true;
                    }

                    $totalAvailableQuests = (int) $publicQuestCount;
                    foreach ($groupIdSet as $gid => $_) {
                        $totalAvailableQuests += (int) ($groupQuestCountByGroupId[(int) $gid] ?? 0);
                    }

                    $gradeSum = 0;
                    $userLatestGrades = $latestGradesByUser[$uid] ?? [];
                    $gradeSumByGroup = [];
                    $completedByGroup = [];
                    foreach ($userLatestGrades as $row) {
                        $groupId = $row['study_group_id'] ?? null;
                        $groupKey = is_null($groupId) ? 0 : (int) $groupId;
                        $isAccessible = is_null($groupId) || isset($groupIdSet[(int) $groupId]);
                        if ($isAccessible) {
                            $grade = (int) ($row['grade'] ?? 0);
                            $gradeSum += $grade;
                            $gradeSumByGroup[$groupKey] = (int) ($gradeSumByGroup[$groupKey] ?? 0) + $grade;

                            if (in_array((string) ($row['status'] ?? ''), ['Approved', 'Rejected'], true)) {
                                $completedByGroup[$groupKey] = (int) ($completedByGroup[$groupKey] ?? 0) + 1;
                            }
                        }
                    }

                    $user->avg_grade = $totalAvailableQuests > 0
                        ? round($gradeSum / $totalAvailableQuests, 1)
                        : 0;

                    $user->total_completed = (int) ($totalCompletedByUser[$uid] ?? 0);
                    // Dashboard.vue reads `user.lvl`
                    $user->lvl = (int) ($user->{$levelColumn} ?? 1);

                    $classAverages = [];

                    if ($publicQuestCount > 0) {
                        $classAverages[] = [
                            'study_group_id' => null,
                            'class_name' => 'General',
                            'average_grade' => round(((int) ($gradeSumByGroup[0] ?? 0)) / $publicQuestCount, 1),
                            'total_quests' => (int) $publicQuestCount,
                            'completed_quests' => (int) ($completedByGroup[0] ?? 0),
                        ];
                    }

                    foreach ($groupIdSet as $groupId => $_) {
                        $totalGroupQuests = (int) ($groupQuestCountByGroupId[(int) $groupId] ?? 0);
                        if ($totalGroupQuests <= 0) {
                            continue;
                        }

                        $classAverages[] = [
                            'study_group_id' => (int) $groupId,
                            'class_name' => (string) ($groupNamesByGroupId[(int) $groupId] ?? "Class {$groupId}"),
                            'average_grade' => round(((int) ($gradeSumByGroup[(int) $groupId] ?? 0)) / $totalGroupQuests, 1),
                            'total_quests' => $totalGroupQuests,
                            'completed_quests' => (int) ($completedByGroup[(int) $groupId] ?? 0),
                        ];
                    }

                    usort($classAverages, function (array $a, array $b): int {
                        $aIsGeneral = is_null($a['study_group_id']);
                        $bIsGeneral = is_null($b['study_group_id']);

                        if ($aIsGeneral !== $bIsGeneral) {
                            return $aIsGeneral ? -1 : 1;
                        }

                        return strcmp((string) ($a['class_name'] ?? ''), (string) ($b['class_name'] ?? ''));
                    });

                    $user->class_averages = $classAverages;

                    return $user;
                });

                $sortedStudents = $allStudents
                    ->sort(function ($a, $b) {
                        $cmp = ((float) ($b->avg_grade ?? 0)) <=> ((float) ($a->avg_grade ?? 0));
                        if ($cmp !== 0) {
                            return $cmp;
                        }

                        $cmp = ((int) ($b->total_completed ?? 0)) <=> ((int) ($a->total_completed ?? 0));
                        if ($cmp !== 0) {
                            return $cmp;
                        }

                        return strcmp((string) ($a->name ?? ''), (string) ($b->name ?? ''));
                    })
                    ->values();

                $perPage = 10;
                $total = $sortedStudents->count();
                $items = $sortedStudents->slice(($page - 1) * $perPage, $perPage)->values();

                return [
                    'items' => $items->map(fn ($u) => [
                        'id' => (int) ($u->id ?? 0),
                        'name' => (string) ($u->name ?? ''),
                        'username' => (string) ($u->username ?? ''),
                        'lvl' => LevelingService::levelFromExp((int) ($u->exp ?? 0)),
                        'avg_grade' => (float) ($u->avg_grade ?? 0),
                        'total_completed' => (int) ($u->total_completed ?? 0),
                        'class_averages' => is_array($u->class_averages ?? null) ? $u->class_averages : [],
                    ])->all(),
                    'total' => (int) $total,
                    'per_page' => (int) $perPage,
                ];
            }
        );

        $students = new LengthAwarePaginator(
            $studentsCachePayload['items'] ?? [],
            (int) ($studentsCachePayload['total'] ?? 0),
            (int) ($studentsCachePayload['per_page'] ?? 10),
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        $helpUsersQuery = User::query()
            ->whereNotIn('role', User::staffRoles());

        if ($isMentor) {
            $helpUsersQuery->where('job_id', $mentorJobId);
        }

        $helpUsers = $isSuperAdmin ? collect() : Cache::remember(
            "dashboard.help_users.v{$dashboardCacheVersion}.{$scopeKey}",
            now()->addMinutes(3),
            function () use ($helpUsersQuery, $levelColumn) {
                return $helpUsersQuery
                    ->withCount([
                        'submissions as graded_count_30d' => function ($query) {
                            $query->whereIn('status', ['Approved', 'Rejected'])
                                ->where('created_at', '>=', now()->subDays(30));
                        },
                    ])
                    ->withAvg([
                        'submissions as avg_grade_30d' => function ($query) {
                            $query->whereIn('status', ['Approved', 'Rejected'])
                                ->where('created_at', '>=', now()->subDays(30));
                        },
                    ], 'grade')
                    ->orderBy('avg_grade_30d')
                    ->take(10)
                    ->get(['id', 'name', 'username', 'exp', $levelColumn])
                    ->map(function ($user) {
                        $user->avg_grade_30d = round((float) ($user->avg_grade_30d ?? 0), 1);
                        $user->lvl = LevelingService::levelFromExp((int) ($user->exp ?? 0));
                        return $user;
                    })
                    ->filter(fn ($user) => (int) ($user->graded_count_30d ?? 0) > 0 && (float) ($user->avg_grade_30d ?? 0) < 75)
                    ->values()
                    ->map(fn ($u) => $u->toArray())
                    ->all();
            }
        );

        $accessibleGroups = $studyGroupAccess
            ->scopeAccessibleGroups(\App\Models\StudyGroup::query(), $authUser)
            ->with('job:id,name')
            ->withCount([
                'staff as staff_count',
                'users as students_count' => fn ($query) => $query->whereNotIn('users.role', User::staffRoles()),
                'quests as quests_count',
                'events as events_count',
            ])
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'description', 'job_id', 'max_members', 'min_level'])
            ->map(fn (\App\Models\StudyGroup $group) => [
                'uuid' => (string) $group->uuid,
                'name' => (string) $group->name,
                'description' => (string) ($group->description ?? ''),
                'job' => $group->job ? [
                    'id' => (int) $group->job->id,
                    'name' => (string) $group->job->name,
                ] : null,
                'students_count' => (int) ($group->students_count ?? 0),
                'staff_count' => (int) ($group->staff_count ?? 0),
                'quests_count' => (int) ($group->quests_count ?? 0),
                'events_count' => (int) ($group->events_count ?? 0),
                'max_members' => (int) ($group->max_members ?? 0),
                'min_level' => (int) ($group->min_level ?? 1),
                'detail_url' => route('groups.detail', $group->uuid),
                'preview_url' => route('groups.user-preview', $group->uuid),
            ])
            ->values();

        $jobCommandItems = $authUser?->isAdmin()
            ? \App\Models\JobRole::query()
                ->withCount(['users', 'studyGroups', 'taskBanks'])
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status', 'description'])
                ->map(fn (\App\Models\JobRole $job) => [
                    'id' => (int) $job->id,
                    'name' => (string) $job->name,
                    'slug' => (string) $job->slug,
                    'status' => (string) ($job->status ?? \App\Models\JobRole::STATUS_ACTIVE),
                    'users_count' => (int) ($job->users_count ?? 0),
                    'study_groups_count' => (int) ($job->study_groups_count ?? 0),
                    'task_banks_count' => (int) ($job->task_banks_count ?? 0),
                    'detail_url' => route('admin.jobs.show', $job->id),
                ])
                ->values()
            : collect();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'students' => $students,
            'helpUsers' => $helpUsers,
            'accessibleGroups' => $accessibleGroups,
            'jobCommandItems' => $jobCommandItems,
            'scope' => [
                'role' => (string) ($authUser?->role ?? ''),
                'job_id' => $isMentor ? $mentorJobId : null,
                'job_name' => $isMentor ? (string) ($authUser?->job?->name ?? '') : null,
            ],
        ]);
    }
}
