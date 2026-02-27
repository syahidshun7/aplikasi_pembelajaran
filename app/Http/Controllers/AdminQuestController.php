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
    public function submissions(Request $request, Quest $quest)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,Pending,Approved,Rejected'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');

        $submissions = $quest->submissions()
            ->with('user')
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('content', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Quests/Admin/Submissions', [
            'quest' => $quest,
            'submissions' => $submissions,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

   
}
