<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\StudyGroup;
use App\Models\UserContentRead;
use App\Services\StudyGroupStaffAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function userIndex(Request $request): Response
    {
        $user = Auth::user();
        $canManageMembership = $user && ! $user->isStaff();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'class_group_id' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $classGroupId = (int) ($validated['class_group_id'] ?? 0);
        $userJobId = $user?->job_id;
        $userGroupIds = $canManageMembership
            ? $user->studyGroups()
                ->where('study_groups.job_id', $userJobId)
                ->pluck('study_groups.id')
                ->toArray()
            : [];
        $availableClassGroups = StudyGroup::query()
            ->whereIn('id', $userGroupIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($classGroupId > 0 && !in_array($classGroupId, $userGroupIds, true)) {
            $classGroupId = 0;
        }

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
            ->when($classGroupId > 0, function ($query) use ($classGroupId) {
                $query->where('study_group_id', $classGroupId);
            })
            ->with('studyGroup:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $guideIds = collect($guides->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $seenGuideIdSet = UserContentRead::seenContentIds((int) ($user?->id ?? 0), UserContentRead::TYPE_GUIDE, $guideIds)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $guides->getCollection()->transform(function (Guide $guide) use ($seenGuideIdSet) {
            $guide->is_new_for_user = $this->isGuideNewForUser($guide, $seenGuideIdSet);
            return $guide;
        });

        return Inertia::render('Guide/UserIndex', [
            'guides' => $guides,
            'filters' => [
                'search' => $search,
                'class_group_id' => $classGroupId > 0 ? $classGroupId : null,
            ],
            'classGroups' => $availableClassGroups,
        ]);
    }

    public function userShow(Guide $guide): Response
    {
        $this->authorizeGuideAccessForCurrentUser($guide);
        if (! Auth::user()?->isStaff()) {
            UserContentRead::markSeen((int) Auth::id(), UserContentRead::TYPE_GUIDE, (int) $guide->id);
        }

        $guide->load('studyGroup:id,name');

        return Inertia::render('Guide/UserShow', [
            'guide' => $guide,
        ]);
    }

    public function userPreview(Request $request, Guide $guide): Response
    {
        $this->authorizeStaffPreviewAccess($request, $guide);
        $guide->load('studyGroup:id,uuid,name');

        return Inertia::render('Guide/UserShow', [
            'guide' => $guide,
            'previewMode' => true,
            'backUrl' => $guide->studyGroup
                ? route('groups.guides.index', $guide->studyGroup->uuid)
                : route('materi.index'),
        ]);
    }

    private function authorizeGuideAccessForCurrentUser(Guide $guide): void
    {
        if (! $guide->study_group_id) {
            return;
        }

        $user = Auth::user();
        $canManageMembership = $user && ! $user->isStaff();

        abort_unless($canManageMembership, 403, 'STAFF_PLAY_MODE_GUIDE_ACCESS_DENIED');

        $userJobId = $user?->job_id;
        $userGroupIds = $user->studyGroups()
            ->where('study_groups.job_id', $userJobId)
            ->pluck('study_groups.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(
            in_array((int) $guide->study_group_id, $userGroupIds, true),
            403,
            'GUIDE_ACCESS_DENIED'
        );
    }

    private function authorizeStaffPreviewAccess(Request $request, Guide $guide): void
    {
        $user = $request->user();

        abort_unless($user?->isStaff(), 403, 'GUIDE_PREVIEW_STAFF_ONLY');

        if (! $guide->study_group_id) {
            abort_unless($user->isAdmin(), 403, 'GLOBAL_GUIDE_PREVIEW_ADMIN_ONLY');
            return;
        }

        $guide->loadMissing('studyGroup');

        abort_unless(
            app(StudyGroupStaffAccessService::class)->canAccess($user, $guide->studyGroup),
            403,
            'GUIDE_PREVIEW_ACCESS_DENIED'
        );
    }

    private function isGuideNewForUser(Guide $guide, array $seenGuideIdSet): bool
    {
        $guideId = (int) $guide->id;
        if ($guideId <= 0 || isset($seenGuideIdSet[$guideId])) {
            return false;
        }

        return $guide->created_at !== null && $guide->created_at->gte(now()->subDays(30));
    }
}
