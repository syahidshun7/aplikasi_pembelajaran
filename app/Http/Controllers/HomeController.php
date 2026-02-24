<?php

namespace App\Http\Controllers;

use App\Models\Quest;
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
    $userId = Auth::id();

    // 1. Ambil Quest dengan status submission (Logika Kelompok Party)
    $userGroupIds = $userId 
        ? Auth::user()->studyGroups()->pluck('study_groups.id')->toArray() 
        : [];

    $quests = Quest::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                  ->orWhereIn('study_group_id', $userGroupIds);
        })
        ->latest()
        ->get()
        ->map(function ($quest) use ($userId) {
            $quest->user_has_submitted = $userId 
                ? $quest->submissions()->where('user_id', $userId)->exists() 
                : false;
            return $quest;
        });

    // 2. Ambil Data Materi / Guide
    $materi = Guide::latest()->get();

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
        ->get()
        ->map(function ($group) use ($userId) {
            $group->is_member = $userId
                ? $group->users()->where('user_id', $userId)->exists()
                : false;
            return $group;
        });

    return Inertia::render('Welcome', [
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
