<?php

namespace App\Http\Controllers;

use Inertia\Inertia;


use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'hero' => [
                'name' => 'Dev_Adventurer',
                'level' => 15,
                'exp' => 65, // dalam persen
                'str' => 20,
                'int' => 45,
                'gold' => '1.250'
            ],
            'quests' => [
                ['id' => 1, 'title' => 'Project Backend', 'xp' => 'High', 'status' => 'Start'],
                ['id' => 2, 'title' => 'Laundry Day', 'xp' => 'Low', 'status' => 'Done'],
                ['id' => 3, 'title' => 'Study React', 'xp' => 'Med', 'status' => 'Start'],
            ],
            'logs' => [
                'You defeated "Bug #404" (+50 XP)',
                'Quest "Buy Milk" Completed'
            ]
        ]);
    }
}