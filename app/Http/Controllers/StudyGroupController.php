<?php
namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class StudyGroupController extends Controller
{
    // Lihat daftar semua party
    public function index()
    {
         $userId = Auth::id();
         $user = Auth::user();
         $userJobId = $user?->job_id;

        $studyGroupCacheVersion = CacheVersion::get('study_groups');
        $jobKey = is_null($userJobId) ? 'none' : (string) (int) $userJobId;

        return Inertia::render('StudyGroups/Index', [
            'groups' => Cache::remember(
                "study_groups.list.v{$studyGroupCacheVersion}.job.{$jobKey}",
                now()->addMinutes(5),
                fn () => StudyGroup::query()
                    ->withCount('users')
                    ->where('job_id', $userJobId)
                    ->latest()
                    ->get()
                    ->map(fn ($group) => $group->toArray())
            ),

            // Mengambil grup milik user melalui query langsung ke Model StudyGroup
            // Ini jauh lebih aman dari error "undefined method"
            'myGroups' => Auth::check()
                ? StudyGroup::where('job_id', $userJobId)
                    ->whereHas('users', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })->withCount('users')->get()
                : []
        ]);
    }

    // Logic JOIN Party
    public function join(Request $request)
    {
        $validated = $request->validate(['invite_code' => 'required|string|max:255']);
        $inviteCode = strtoupper(trim($validated['invite_code']));
        $userId = (int) Auth::id();

        $group = StudyGroup::where('invite_code', $inviteCode)->first();
        if (! $group) {
            return back()->withErrors(['invite_code' => 'KODE_INVALID: Party tidak ditemukan.']);
        }

        $user = Auth::user();
        if ((int) $group->job_id !== (int) ($user->job_id ?? 0)) {
            // Jangan bocorkan detail mismatch jobs, tampilkan seperti kode tidak valid.
            return back()->withErrors(['invite_code' => 'KODE_INVALID: Party tidak ditemukan.']);
        }

        if ($group->users()->where('user_id', $userId)->exists()) {
            return back()->withErrors(['invite_code' => 'ALREADY_MEMBER: Kamu sudah di dalam party ini.']);
        }

        $joinRequest = StudyGroupJoinRequest::firstOrNew([
            'study_group_id' => $group->id,
            'user_id' => $userId,
        ]);

        if ($joinRequest->exists && $joinRequest->status === 'pending') {
            return back()->withErrors(['invite_code' => 'REQUEST_PENDING: Menunggu persetujuan admin group.']);
        }

        $joinRequest->status = 'pending';
        $joinRequest->processed_by = null;
        $joinRequest->save();

        return back()->with('message', 'JOIN_REQUEST_SENT_WAITING_APPROVAL');
    }

    // Logic LEAVE Party
    public function leave($uuid)
    {
        $group = StudyGroup::where('uuid', $uuid)->firstOrFail();
        $group->users()->detach(Auth::id());

        return back()->with('message', 'LEFT_THE_PARTY');
    }
}
