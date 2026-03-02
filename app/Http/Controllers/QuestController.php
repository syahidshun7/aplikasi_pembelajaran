<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Submission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\StudyGroup;
use Illuminate\Support\Str;

class QuestController extends Controller
{
    public function userIndex(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $userId = auth()->id();
        $userGroupIds = auth()->user()->studyGroups()->pluck('study_groups.id')->toArray();
        $submittedQuestIds = Submission::where('user_id', $userId)->pluck('quest_id')->toArray();

        $quests = Quest::query()
            ->where(function ($query) use ($userGroupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('difficulty', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('studyGroup', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with('studyGroup:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $quests->through(function ($quest) use ($submittedQuestIds) {
            $quest->user_has_submitted = in_array($quest->id, $submittedQuestIds, true);
            return $quest;
        });

        return Inertia::render('Quests/UserIndex', [
            'quests' => $quests,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        return Inertia::render('Quests/Index', [
            'quests' => Quest::query()
                ->with('studyGroup')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('difficulty', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhereHas('studyGroup', function ($sq) use ($search) {
                                $sq->where('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),

            'studyGroups' => StudyGroup::select('id', 'name')->get(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'difficulty' => 'required|in:C-Rank,B-Rank,A-Rank,S-Rank',
            'reward_gold' => 'nullable|integer|min:0',
            'reward_exp' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:Available,In-Progress,Done',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'deadline' => 'nullable|date', // Tambahkan validasi date
        ]);

        $goldTable = [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ];

        $validated['reward_gold'] = $goldTable[$request->difficulty] ?? 0;
        $validated['reward_exp'] = $goldTable[$request->difficulty] ?? 0;
        $validated['status'] = $request->filled('deadline')
            ? (\Carbon\Carbon::parse($request->deadline)->isFuture() ? 'Available' : 'Done')
            : 'Available';
        $validated['uuid'] = (string) \Illuminate\Support\Str::uuid();

        Quest::create($validated);

        return redirect()->back()->with('message', 'NEW_QUEST_DEPLOYED_SUCCESSFULLY');
    }

    public function update(Request $request, $uuid)
    {
        $quest = Quest::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'difficulty' => 'required|in:C-Rank,B-Rank,A-Rank,S-Rank',
            'description' => 'nullable|string',
            'reward_gold' => 'required|integer|min:0',
            'reward_exp' => 'nullable|integer|min:0',
            'status' => 'required|in:Available,In-Progress,Done',
            'study_group_id' => 'nullable|exists:study_groups,id',
            'deadline' => 'nullable|date', // Tambahkan validasi date
        ]);

        $goldTable = [
            'S-Rank' => 5000,
            'A-Rank' => 2500,
            'B-Rank' => 1000,
            'C-Rank' => 500,
        ];

        // Logika update gold jika difficulty berubah
        $validated['reward_gold'] = $goldTable[$request->difficulty] ?? $validated['reward_gold'];
        $validated['reward_exp'] = $goldTable[$request->difficulty] ?? ($validated['reward_exp'] ?? 0);
        $validated['status'] = $request->filled('deadline')
            ? (\Carbon\Carbon::parse($request->deadline)->isFuture() ? 'Available' : 'Done')
            : 'Available';

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
        $submission = $quest->submissions()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        return Inertia::render('Quests/Show', [
            'quest' => $quest,
            'hasSubmitted' => !!$submission,
            'existingSubmission' => $submission // Kirim datanya ke Vue
        ]);
    }
}
