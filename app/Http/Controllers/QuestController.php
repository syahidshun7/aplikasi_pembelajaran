<?php
namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Quest;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Dashboard', [ // Mencari resources/js/Pages/Dashboard.vue
            'hero' => [
                'name'  => $user->name,
                'level' => 10,
                'gold'  => 500,
                'exp'   => 40, // Mengisi bar XP
                'str'   => 15,
                'int'   => 12,
            ],
            'quests' => Quest::where('is_completed', false)->get()->map(function($q) {
                return [
                    'id'    => $q->id,
                    'title' => $q->title,
                    'xp'    => $q->difficulty, // Pastikan ini 'High', 'Med', atau 'Low'
                    'status'=> 'Start'
                ];
            }),
            'logs' => [
                "Hero {$user->name} has entered the realm.",
                "System: Welcome to P-Quest Dashboard."
            ]
        ]);
    }
}