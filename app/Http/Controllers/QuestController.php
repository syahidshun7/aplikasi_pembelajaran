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
            'reward_gold' => 'required|integer',
        ]);

        Quest::create($validated);

        return redirect()->back()->with('message', 'New Quest Added to Board!');
    }

    public function update(Request $request, Quest $quest)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'difficulty' => 'required',
            'reward_gold' => 'required|integer',
        ]);

        $quest->update($validated);

        return redirect()->back()->with('message', 'Quest metadata updated successfully.');
    }

    public function destroy(Quest $quest)
    {
        $quest->delete();

        return redirect()->back()->with('message', 'Mission aborted and removed from board.');
    }
}
