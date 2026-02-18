<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
       $userId = Auth::id();

        // Ambil Quest dan tambahkan status apakah user sudah submit
        $quests = Quest::where('status', 'available')
            ->latest()
            ->get()
            ->map(function ($quest) use ($userId) {
                // Kita tambahkan properti 'user_has_submitted' secara on-the-fly
                $quest->user_has_submitted = $userId 
                    ? $quest->submissions()->where('user_id', $userId)->exists() 
                    : false;
                return $quest;
            });

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'quests' => $quests, // Kirim data quest yang sudah diproses
            'players' => User::select('id', 'name')->latest()->get(),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    }
}