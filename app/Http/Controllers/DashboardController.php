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
        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : 'level';

        $stats = [
            'total_materi' => Guide::count(),
            'total_students' => User::where('role', '!=', 'admin')->count(),
            'pending_verdicts' => Submission::where('status', 'Pending')->count(),
        ];

        $topUsers = User::query()
            ->where('role', '!=', 'admin')
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
            ->take(10)
            ->get(['id', 'name', 'username', 'role', 'exp', 'gold', $levelColumn])
            ->map(function ($user) use ($levelColumn) {
                $user->avg_grade = round((float) ($user->avg_grade ?? 0), 1);
                // Dashboard.vue currently reads `user.lvl`
                $user->lvl = (int) ($user->{$levelColumn} ?? 1);
                return $user;
            })
            ->filter(fn ($user) => $user->avg_grade > 75)
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'topUsers' => $topUsers,
            'recentLogs' => [
                'Admin session started at ' . now()->format('H:i'),
                'Elite Performers Monitor updated.',
            ],
        ]);
    }
}
