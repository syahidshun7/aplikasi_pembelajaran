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
        $isStaffPlayMode = (bool) $user?->isStaffPlayMode();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $userGroupIds = $isStaffPlayMode
            ? []
            : $user->studyGroups()->pluck('study_groups.id')->toArray();

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

    public function userShow(Guide $guide): Response
    {
        $this->authorizeGuideAccessForCurrentUser($guide);
        $guide->load('studyGroup:id,name');

        return Inertia::render('Guide/UserShow', [
            'guide' => $guide,
        ]);
    }

    private function authorizeGuideAccessForCurrentUser(Guide $guide): void
    {
        if (! $guide->study_group_id) {
            return;
        }

        abort_if((bool) Auth::user()?->isStaffPlayMode(), 403, 'STAFF_PLAY_MODE_GUIDE_ACCESS_DENIED');

        $userGroupIds = Auth::user()
            ->studyGroups()
            ->pluck('study_groups.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(
            in_array((int) $guide->study_group_id, $userGroupIds, true),
            403,
            'GUIDE_ACCESS_DENIED'
        );
    }
}
