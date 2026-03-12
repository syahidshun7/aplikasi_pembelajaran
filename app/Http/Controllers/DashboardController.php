<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Support\Cache\CacheVersion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $isMentor = (bool) $authUser?->isMentor();
        $mentorJobId = (int) ($authUser?->job_id ?? 0);

        if ($isMentor && $mentorJobId <= 0) {
            abort(403, 'MENTOR_JOB_REQUIRED');
        }

        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : 'level';

        $guideQuery = Guide::query();
        $studentQuery = User::query()->whereNotIn('role', ['admin', 'mentor']);
        $pendingSubmissionQuery = Submission::query()->where('status', 'Pending');
        $gradedSubmissionQuery = Submission::query()->whereIn('status', ['Approved', 'Rejected']);

        if ($isMentor) {
            $guideQuery->whereHas('studyGroup', function ($q) use ($mentorJobId) {
                $q->where('job_id', $mentorJobId);
            });

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
            "dashboard.stats.v{$dashboardCacheVersion}.{$scopeKey}",
            now()->addMinutes(3),
            function () use ($guideQuery, $studentQuery, $pendingSubmissionQuery, $gradedSubmissionQuery) {
                $avgGrade30d = (float) (clone $gradedSubmissionQuery)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->avg('grade');

                $graded7dCount = (int) (clone $gradedSubmissionQuery)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();

                return [
                    'total_materi' => (int) $guideQuery->count(),
                    'total_students' => (int) $studentQuery->count(),
                    'pending_verdicts' => (int) $pendingSubmissionQuery->count(),
                    'avg_grade_30d' => round($avgGrade30d, 1),
                    'graded_7d' => $graded7dCount,
                ];
            }
        );

        $page = LengthAwarePaginator::resolveCurrentPage();
        $studentsCachePayload = Cache::remember(
            "dashboard.students.v{$dashboardCacheVersion}.{$scopeKey}.page.{$page}",
            now()->addMinutes(3),
            function () use ($isMentor, $mentorJobId, $levelColumn, $page) {
                $studentsQuery = User::query()
                    ->whereNotIn('role', ['admin', 'mentor']);

                if ($isMentor) {
                    $studentsQuery->where('job_id', $mentorJobId);
                }

                // NOTE: Sort + perhitungan avg_grade harus konsisten dengan "grade average" di sisi user/profile:
                // avg_grade = (sum latest grade per available quest) / (total available quests),
                // di mana quest yang belum disubmit tetap masuk denominator (nilai 0).
                $allStudents = $studentsQuery->get(['id', 'name', 'username', $levelColumn]);
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
                    ->count();

                $groupQuestCountByGroupId = Quest::query()
                    ->whereNotNull('study_group_id')
                    ->selectRaw('study_group_id, COUNT(*) as cnt')
                    ->groupBy('study_group_id')
                    ->pluck('cnt', 'study_group_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $userGroupIdsMap = DB::table('group_user')
                    ->whereIn('user_id', $userIds)
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
                    ->get(['submissions.user_id', 'submissions.grade', 'quests.study_group_id']);

                foreach ($latestSubmissions as $submission) {
                    $uid = (int) $submission->user_id;
                    $latestGradesByUser[$uid][] = [
                        'study_group_id' => is_null($submission->study_group_id) ? null : (int) $submission->study_group_id,
                        'grade' => (int) ($submission->grade ?? 0),
                    ];
                }

                $allStudents->transform(function ($user) use ($levelColumn, $publicQuestCount, $groupQuestCountByGroupId, $userGroupIdsMap, $totalCompletedByUser, $latestGradesByUser) {
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
                    foreach ($userLatestGrades as $row) {
                        $groupId = $row['study_group_id'] ?? null;
                        if (is_null($groupId) || isset($groupIdSet[(int) $groupId])) {
                            $gradeSum += (int) ($row['grade'] ?? 0);
                        }
                    }

                    $user->avg_grade = $totalAvailableQuests > 0
                        ? round($gradeSum / $totalAvailableQuests, 1)
                        : 0;

                    $user->total_completed = (int) ($totalCompletedByUser[$uid] ?? 0);
                    // Dashboard.vue reads `user.lvl`
                    $user->lvl = (int) ($user->{$levelColumn} ?? 1);

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
                    'items' => $items->map(fn ($u) => $u->toArray())->all(),
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
            ->whereNotIn('role', ['admin', 'mentor']);

        if ($isMentor) {
            $helpUsersQuery->where('job_id', $mentorJobId);
        }

        $helpUsers = Cache::remember(
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
                    ->get(['id', 'name', 'username', $levelColumn])
                    ->map(function ($user) use ($levelColumn) {
                        $user->avg_grade_30d = round((float) ($user->avg_grade_30d ?? 0), 1);
                        $user->lvl = (int) ($user->{$levelColumn} ?? 1);
                        return $user;
                    })
                    ->filter(fn ($user) => (int) ($user->graded_count_30d ?? 0) > 0 && (float) ($user->avg_grade_30d ?? 0) < 75)
                    ->values()
                    ->map(fn ($u) => $u->toArray())
                    ->all();
            }
        );

        $pendingSubmissions = $pendingSubmissionQuery
            ->with([
                'user:id,name,username',
                'quest:id,uuid,title',
            ])
            ->latest('id')
            ->take(12)
            ->get()
            ->map(function (Submission $submission) {
                return [
                    'uuid' => (string) $submission->uuid,
                    'created_at' => optional($submission->created_at)->toISOString(),
                    'user' => [
                        'id' => (int) $submission->user_id,
                        'name' => (string) ($submission->user?->name ?? 'Unknown'),
                        'username' => (string) ($submission->user?->username ?? ''),
                    ],
                    'quest' => [
                        'uuid' => (string) ($submission->quest?->uuid ?? ''),
                        'title' => (string) ($submission->quest?->title ?? 'Unknown Quest'),
                    ],
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'students' => $students,
            'helpUsers' => $helpUsers,
            'pendingSubmissions' => $pendingSubmissions,
            'scope' => [
                'role' => (string) ($authUser?->role ?? ''),
                'job_id' => $isMentor ? $mentorJobId : null,
                'job_name' => $isMentor ? (string) ($authUser?->job?->name ?? '') : null,
            ],
            'recentLogs' => [
                ($isMentor ? 'Mentor session started at ' : 'Admin session started at ') . now()->format('H:i'),
                $isMentor ? ('Scope locked to job_id=' . $mentorJobId) : 'Global scope enabled.',
            ],
        ]);
    }
}
