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

        // 1. Ambil Quest dengan status submission user
       $userId = Auth::id();
    
    // Ambil ID semua kelompok yang diikuti user
    $userGroupIds = $userId 
        ? Auth::user()->studyGroups()->pluck('study_groups.id')->toArray() 
        : [];

    $quests = Quest::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id') // Ambil quest umum
                  ->orWhereIn('study_group_id', $userGroupIds); // Ambil quest milik party user
        })
        ->latest()
        ->get()
        ->map(function ($quest) use ($userId) {
            $quest->user_has_submitted = $userId 
                ? $quest->submissions()->where('user_id', $userId)->exists() 
                : false;
            return $quest;
        });
        // 2. Ambil Data Materi / Guide untuk Library
        $materi = Guide::latest()->get();

        // 3. Ambil Data Player (Sedikit tambahan: berikan lvl dummy jika belum ada kolomnya di DB)
        $players = User::select('id', 'name')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($player) {
                $player->lvl = rand(1, 99);
                $player->job = 'Adventurer';
                return $player;
            });

        // 4. BARU: Ambil Data Kelompok Belajar (Study Groups)
        // Kita hitung jumlah member di tiap grup dengan withCount
        // Di LobbyController / WelcomeController
        $studyGroups = \App\Models\StudyGroup::withCount('users')
            ->latest()
            ->get()
            ->map(function ($group) use ($userId) {
                // Cek apakah user id ada di tabel pivot group_user
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
            'players' => $players,
            'studyGroups' => $studyGroups, // Data kelompok belajar dikirim ke sini
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    }
}
