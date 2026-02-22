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
    $quests = Quest::latest()
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
        ->take(10) // Batasi 10 player terakhir agar tidak terlalu berat
        ->get()
        ->map(function ($player) {
            // Kita tambahkan data dummy agar tampilan lobby tetap keren
            $player->lvl = rand(1, 99); 
            $player->job = 'Adventurer'; 
            return $player;
        });

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'quests' => $quests,
        'materi' => $materi, // Data Library dikirim ke sini
        'players' => $players,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
}
}