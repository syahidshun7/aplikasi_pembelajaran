<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminQuestController extends Controller
{
    /**
     * Menampilkan daftar semua submission untuk quest tertentu.
     */
    public function submissions(Quest $quest)
    {
        // Load submissions dengan data user-nya
        $submissions = $quest->submissions()
            ->with('user')
            ->latest()
            ->get();

        return Inertia::render('Quests/Admin/Submissions', [
            'quest' => $quest,
            'submissions' => $submissions
        ]);
    }

   
}