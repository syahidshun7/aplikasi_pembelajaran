<?php

namespace App\Http\Controllers;

use App\Models\JobRole;
use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class AdminStudyGroupController extends Controller
{
     public function manage(Request $request)
{
    // Pastikan hanya admin boleh masuk
    if (!auth()->user()->isAdmin()) {
        abort(403, 'Hanya Admin (Grandmaster) yang dibenarkan masuk ke Command Center!');
    }

    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'view' => ['nullable', 'in:active,trash'],
    ]);
    $search = trim((string) ($validated['search'] ?? ''));
    $view = (string) ($validated['view'] ?? 'active');

    return Inertia::render('StudyGroups/Admin/Index', [
        'groups' => StudyGroup::query()
            ->when($view === 'trash', fn ($query) => $query->onlyTrashed())
            ->with('job:id,name')
            ->withCount([
                'users as users_count' => fn ($userQuery) => $userQuery->whereNotIn('users.role', User::staffRoles()),
                'joinRequests as pending_requests_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('invite_code', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString(),
        'filters' => [
            'search' => $search,
            'view' => $view,
        ],
        'jobs' => JobRole::query()->orderBy('name')->get(['id', 'name', 'slug']),
    ]);
}
     
    public function store(Request $request)
    {
        // Pastikan hanya admin (sesuaikan dengan logic middleware/role kamu)
        if (!Auth::user()->isAdmin()) {
            abort(403, 'UNAUTHORIZED_ACCESS');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'required|integer|min:1|max:50',
            'job_id' => 'required|exists:job_roles,id',
        ]);

        // Karena Model StudyGroup sudah pakai HasUuids, 
        // kita tidak perlu menulis 'uuid' => Str::uuid() di sini.
        StudyGroup::create([
            'name' => $request->name,
            'description' => $request->description,
            'max_members' => $request->max_members,
            'job_id' => (int) $request->job_id,
            'invite_code' => $this->generateUniqueInviteCode(),
        ]);

        return back()->with('message', 'NEW_PARTY_ESTABLISHED');
    }


    public function update(Request $request, $uuid)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $oldJobId = (int) ($group->job_id ?? 0);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_members' => 'required|integer|min:1|max:50',
            'job_id' => 'required|exists:job_roles,id',
        ]);

        $newJobId = (int) $request->job_id;

        if ($newJobId !== $oldJobId) {
            $memberIds = $group->users()->pluck('users.id');

            if ($memberIds->isNotEmpty()) {
                $hasConflicts = StudyGroup::query()
                    ->where('id', '!=', $group->id)
                    ->whereIn('id', function ($q) use ($memberIds) {
                        $q->from('group_user')
                            ->select('study_group_id')
                            ->whereIn('user_id', $memberIds)
                            ->whereNull('deleted_at');
                    })
                    ->where('job_id', '!=', $newJobId)
                    ->exists();

                if ($hasConflicts) {
                    return back()->withErrors([
                        'job_id' => 'JOB_CONFLICT: Some current members belong to groups with different jobs.',
                    ]);
                }
            }
        }

        $group->update([
            'name' => $request->name,
            'description' => $request->description,
            'max_members' => $request->max_members,
            'job_id' => $newJobId,
        ]);

        if ($newJobId !== $oldJobId) {
            User::query()
                ->whereIn('id', function ($q) use ($group) {
                    $q->from('group_user')
                        ->select('user_id')
                        ->where('study_group_id', $group->id)
                        ->whereNull('deleted_at');
                })
                ->update(['job_id' => $newJobId]);
        }

        return back()->with('message', 'DATA_UPDATED_SUCCESSFULLY');
    }

    public function detail($uuid)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        $members = $group->users()
            ->select('users.id', 'users.name', 'users.username', 'users.email')
            ->orderBy('users.name')
            ->get();

        $requests = $group->joinRequests()
            ->with('user:id,name,username,email')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return Inertia::render('StudyGroups/Admin/Detail', [
            'group' => $group,
            'members' => $members,
            'requests' => $requests,
        ]);
    }

    public function approveRequest($uuid, $requestId)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $joinRequest = StudyGroupJoinRequest::where('id', $requestId)
            ->where('study_group_id', $group->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $member = User::query()->findOrFail((int) $joinRequest->user_id);
        $isStaffMember = (bool) $member->isStaff();
        $activePlayerCount = (int) $group->users()
            ->whereNotIn('users.role', User::staffRoles())
            ->count();

        if (! $isStaffMember && $activePlayerCount >= (int) $group->max_members) {
            return back()->withErrors(['group' => 'PARTY_FULL: Kapasitas player sudah penuh.']);
        }

        $groupJobId = (int) ($group->job_id ?? 0);

        if ($groupJobId > 0) {
            $hasOtherJobGroups = StudyGroup::query()
                ->whereIn('id', function ($q) use ($member) {
                    $q->from('group_user')
                        ->select('study_group_id')
                        ->where('user_id', $member->id)
                        ->whereNull('deleted_at');
                })
                ->where('job_id', '!=', $groupJobId)
                ->exists();

            if ($hasOtherJobGroups) {
                return back()->withErrors(['group' => 'MEMBER_JOB_CONFLICT: User belongs to another job path group.']);
            }

            if ((int) ($member->job_id ?? 0) !== $groupJobId) {
                $member->job_id = $groupJobId;
                $member->save();
            }
        }

        $group->attachOrRestoreMember((int) $joinRequest->user_id, $member->isMentor() ? 'mentor_observer' : 'member');

        $joinRequest->update([
            'status' => 'approved',
            'processed_by' => Auth::id(),
        ]);

        return back()->with('message', 'REQUEST_APPROVED_MEMBER_ADDED');
    }

    public function rejectRequest($uuid, $requestId)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $joinRequest = StudyGroupJoinRequest::where('id', $requestId)
            ->where('study_group_id', $group->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $joinRequest->update([
            'status' => 'rejected',
            'processed_by' => Auth::id(),
        ]);

        return back()->with('message', 'REQUEST_REJECTED');
    }

    public function removeMember($uuid, $userId)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        $group->softRemoveMember((int) $userId);
        StudyGroupJoinRequest::where('study_group_id', $group->id)
            ->where('user_id', (int) $userId)
            ->update([
                'status' => 'rejected',
                'processed_by' => Auth::id(),
            ]);

        return back()->with('message', 'MEMBER_REMOVED_FROM_GROUP');
    }

    /**
     * ADMIN ACTION: Disband/Delete a study group.
     */
    public function destroy($uuid)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();

        // Pivot table otomatis terhapus jika di migration kamu pakai onDelete('cascade')
        $group->delete();

        return back()->with('message', 'PARTY_DISBANDED');
    }

    public function restore(string $uuid)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $group = StudyGroup::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $group->restore();

        return back()->with('message', 'PARTY_RESTORED');
    }

    public function forceDestroy(string $uuid)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $group = StudyGroup::onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $group->forceDelete();

        return back()->with('message', 'PARTY_PERMANENTLY_DELETED');
    }

    private function generateUniqueInviteCode(): string
    {
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = 'GRP-' . strtoupper(Str::random(6));

            if (! StudyGroup::where('invite_code', $code)->exists()) {
                return $code;
            }
        }

        abort(500, 'FAILED_TO_GENERATE_UNIQUE_INVITE_CODE');
    }

   
}
