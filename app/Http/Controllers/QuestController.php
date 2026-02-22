<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestController extends Controller
{
    public function index()
    {
        return Inertia::render('Quests/Index', [
            'quests' => Quest::all() // Mengirim data quest ke Vue
        ]);
    }

   public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'difficulty' => 'required',
        'reward_gold' => 'nullable|integer', // Buat nullable karena akan kita timpa/isi otomatis
        'description' => 'nullable|string',
        'status' => 'required',
    ]);

    $goldTable = [
        'S-Rank' => 5000,
        'A-Rank' => 2500,
        'B-Rank' => 1000,
        'C-Rank' => 500,
        'D-Rank' => 100,
    ];

    // Otomatis isi reward_gold ke dalam array validated berdasarkan difficulty
    $validated['reward_gold'] = $goldTable[$request->difficulty] ?? ($request->reward_gold ?? 0);

    // Simpan hanya satu kali dengan data lengkap
    Quest::create($validated);

    return redirect()->back()->with('message', 'New Quest Added to Board!');
}

    public function update(Request $request, Quest $quest)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'difficulty' => 'required',
            'description' => 'nullable|string',
            'reward_gold' => 'required|integer',
            'status' => 'required',
        ]);

        $quest->update($validated);

        return redirect()->back()->with('message', 'Quest metadata updated successfully.');
    }

    public function destroy(Quest $quest)
    {
        $quest->delete();

        return redirect()->back()->with('message', 'Mission aborted and removed from board.');
    }


    public function show(Quest $quest)
    {
        $submission = $quest->submissions()->where('user_id', auth()->id())->first();

        return Inertia::render('Quests/Show', [
            'quest' => $quest,
            'hasSubmitted' => !!$submission,
            'existingSubmission' => $submission // Kirim datanya ke Vue
        ]);
    }

    
}
