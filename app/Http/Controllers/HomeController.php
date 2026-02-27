<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use App\Models\Guide; // Ganti dengan nama model materimu jika berbeda
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;

class HomeController extends Controller
{
   public function index()
{
    if (!Auth::check()) {
        return Inertia::render('Landing', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    }

    $userId = Auth::id();

    // 1. Ambil Quest dengan status submission (Logika Kelompok Party)
    $userGroupIds = $userId 
        ? Auth::user()->studyGroups()->pluck('study_groups.id')->toArray() 
        : [];
    $userSubmissions = $userId
        ? Submission::where('user_id', $userId)
            ->latest('id')
            ->get(['quest_id', 'status'])
            ->unique('quest_id')
        : collect();

    $submittedQuestIds = $userSubmissions->pluck('quest_id')->toArray();
    $submissionStatusesByQuest = $userSubmissions->pluck('status', 'quest_id')->toArray();

    $quests = Quest::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                  ->orWhereIn('study_group_id', $userGroupIds);
        })
        ->latest()
        ->take(10)
        ->get()
        ->map(function ($quest) use ($submittedQuestIds, $submissionStatusesByQuest) {
            $quest->user_has_submitted = in_array($quest->id, $submittedQuestIds, true);
            $quest->user_submission_status = $submissionStatusesByQuest[$quest->id] ?? null;
            return $quest;
        });

    // 2. Ambil Data Materi / Guide (Global + sesuai study group user)
    $materi = Guide::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                ->orWhereIn('study_group_id', $userGroupIds);
        })
        ->with('studyGroup:id,name')
        ->latest()
        ->take(10)
        ->get();

    // 3. UPDATE: Ambil Data Player Berdasarkan EXP Tertinggi (Leaderboard)
    $players = User::select('id', 'name', 'username', 'profile_photo', 'level', 'exp', 'role')
        ->orderBy('exp', 'desc') // Mengurutkan dari EXP paling tinggi ke rendah
        ->take(10)               // Ambil Top 10 Player
        ->get()
        ->map(function ($player) {
            // Jika kolom lvl atau role kosong, berikan fallback
            $player->lvl = $player->lvl ?? 1;
            $player->role = $player->role ?? 'Adventurer';
            return $player;
        });

    // 4. Ambil Data Kelompok Belajar (Study Groups)
    $studyGroups = \App\Models\StudyGroup::withCount('users')
        ->latest()
        ->get();

    $groupRequestStatuses = $userId
        ? StudyGroupJoinRequest::where('user_id', $userId)->pluck('status', 'study_group_id')->toArray()
        : [];

    $studyGroups = $studyGroups->map(function ($group) use ($userGroupIds, $groupRequestStatuses) {
            $group->is_member = in_array($group->id, $userGroupIds, true);
            $group->join_request_status = $groupRequestStatuses[$group->id] ?? null;
            return $group;
        });

    return Inertia::render('home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'quests' => $quests,
        'materi' => $materi,
        'players' => $players, // Sekarang berisi Top 10 pemain terkuat
        'studyGroups' => $studyGroups,
        'laravelVersion' => \Illuminate\Foundation\Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
}
}
