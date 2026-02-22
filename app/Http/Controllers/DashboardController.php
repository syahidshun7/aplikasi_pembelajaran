<?php

namespace App\Http\Controllers;
use App\Models\Quest;
use App\Models\User;
use Inertia\Inertia;


use Illuminate\Http\Request;

class DashboardController extends Controller
{
  public function index()
{
  // 1. Ambil Statistik Umum
    $stats = [
        // 'total_materi' => \App\Models\Materi::count(),
        'total_students' => \App\Models\User::where('role', 'student')->count(),
        'pending_verdicts' => \App\Models\Submission::where('status', 'Pending')->count(),
    ];

    // 2. Query User yang memiliki rata-rata Grade > 75
    // Asumsi: tabel submissions punya kolom 'grade' dan 'user_id'
    $topUsers = \App\Models\User::where('role', 'student')
        ->withCount(['submissions as total_completed' => function ($query) {
            $query->whereIn('status', ['Approved', 'Rejected']);
        }])
        ->get()
        ->map(function ($user) {
            // Hitung rata-rata grade dari submission yang sudah dinilai
            $avg = $user->submissions()->whereIn('status', ['Approved', 'Rejected'])->avg('grade');
            $user->avg_grade = round($avg ?? 0, 1);
            return $user;
        })
        ->filter(fn($user) => $user->avg_grade > 75) // Filter Grade > 75
        ->sortByDesc('avg_grade')
        ->values();

    return Inertia::render('Admin/Dashboard', [
        'stats' => $stats,
        'topUsers' => $topUsers,
        'recentLogs' => [
            "Admin session started at " . now()->format('H:i'),
            "Elite Performers Monitor updated.",
        ]
    ]);
}
}