<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function userIndex(Request $request): Response
    {
        $user = Auth::user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $userGroupIds = $user->studyGroups()->pluck('study_groups.id')->toArray();

        $guides = Guide::query()
            ->where(function ($query) use ($userGroupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('studyGroup', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with('studyGroup:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Guide/UserIndex', [
            'guides' => $guides,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
