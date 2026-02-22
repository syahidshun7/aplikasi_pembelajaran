<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\StudyGroup;
use Illuminate\Support\Str;

class QuestController extends Controller
{
   public function index()
{
    return Inertia::render('Quests/Index', [
        // 1. Ambil semua quest beserta data kelompoknya (Eager Loading)
        'quests' => Quest::with('studyGroup')
            ->latest()
            ->get(),

        // 2. Kirim daftar kelompok untuk pilihan di dropdown form
        'studyGroups' => StudyGroup::select('id', 'name')->get(),
    ]);
}

   public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'difficulty' => 'required',
        'reward_gold' => 'nullable|integer', 
        'description' => 'nullable|string',
        'status' => 'required',
        'study_group_id' => 'nullable|exists:study_groups,id',
    ]);

    $goldTable = [
        'S-Rank' => 5000,
        'A-Rank' => 2500,
        'B-Rank' => 1000,
        'C-Rank' => 500,
        'D-Rank' => 100,
    ];

    // Sinkronisasi Gold di sisi server (Security Check)
    $validated['reward_gold'] = $goldTable[$request->difficulty] ?? 0;
    
    // GENERATE UUID: Penting jika tabel menggunakan UUID sebagai primary/lookup key
    $validated['uuid'] = (string) Str::uuid();

    Quest::create($validated);

    return redirect()->back()->with('message', 'NEW_QUEST_DEPLOYED_SUCCESSFULLY');
}

public function update(Request $request, $uuid)
{
    // Cari berdasarkan UUID karena Vue mengirimkan editId (uuid)
    $quest = Quest::where('uuid', $uuid)->firstOrFail();

    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'difficulty' => 'required',
        'description' => 'nullable|string',
        'reward_gold' => 'required|integer',
        'status' => 'required',
        'study_group_id' => 'nullable|exists:study_groups,id', // Tambahkan ini agar bisa diupdate
    ]);

    // Update Gold otomatis jika admin mengubah difficulty saat edit
    $goldTable = [
        'S-Rank' => 5000,
        'A-Rank' => 2500,
        'B-Rank' => 1000,
        'C-Rank' => 500,
        'D-Rank' => 100,
    ];
    $validated['reward_gold'] = $goldTable[$request->difficulty] ?? $validated['reward_gold'];

    $quest->update($validated);

    return redirect()->back()->with('message', 'QUEST_CONTRACT_SYNCHRONIZED');
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
