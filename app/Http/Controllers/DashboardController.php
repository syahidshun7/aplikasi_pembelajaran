<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\Submission;
use App\Models\User;
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

        $avgGrade30d = (float) (clone $gradedSubmissionQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('grade');

        $graded7dCount = (int) (clone $gradedSubmissionQuery)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $stats = [
            'total_materi' => (int) $guideQuery->count(),
            'total_students' => (int) $studentQuery->count(),
            'pending_verdicts' => (int) $pendingSubmissionQuery->count(),
            'avg_grade_30d' => round($avgGrade30d, 1),
            'graded_7d' => $graded7dCount,
        ];

        $studentsQuery = User::query()
            ->whereNotIn('role', ['admin', 'mentor']);

        if ($isMentor) {
            $studentsQuery->where('job_id', $mentorJobId);
        }

        $students = $studentsQuery
            ->withCount([
                'submissions as total_completed' => function ($query) {
                    $query->whereIn('status', ['Approved', 'Rejected']);
                },
            ])
            ->withAvg([
                'submissions as avg_grade' => function ($query) {
                    $query->whereIn('status', ['Approved', 'Rejected']);
                },
            ], 'grade')
            ->orderByDesc('avg_grade')
            ->orderByDesc('total_completed')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $students->getCollection()->transform(function ($user) use ($levelColumn) {
            $user->avg_grade = round((float) ($user->avg_grade ?? 0), 1);
            // Dashboard.vue reads `user.lvl`
            $user->lvl = (int) ($user->{$levelColumn} ?? 1);
            return $user;
        });

        $helpUsersQuery = User::query()
            ->whereNotIn('role', ['admin', 'mentor']);

        if ($isMentor) {
            $helpUsersQuery->where('job_id', $mentorJobId);
        }

        $helpUsers = $helpUsersQuery
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
            ->values();

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
