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
    return Inertia::render('Lobby', [
        'quests' => Quest::where('status', 'available')->get(),
        'players' => User::where('is_online', true)->get(),
    ]);
}
}