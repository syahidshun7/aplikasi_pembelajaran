<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\JobRole;
use App\Models\Quest;
use App\Models\Submission;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // 1. Ambil semua quest yang tersedia untuk user: public + party user.
        $userGroupIds = $user->studyGroups()->pluck('study_groups.id')->toArray();
        $availableQuestIds = Quest::query()
            ->where(function ($query) use ($userGroupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
            ->pluck('id');

        $totalAvailableQuests = $availableQuestIds->count();

        // 2. Ambil submission terbaru user per quest untuk basis scoring.
        $latestSubmissions = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('quest_id', $availableQuestIds)
            ->orderByDesc('id')
            ->get(['quest_id', 'grade', 'status'])
            ->unique('quest_id');

        // 3. Overall grade = total grade submission user / total quest tersedia.
        // Quest yang belum disubmit otomatis bernilai 0 karena tetap masuk denominator.
        $gradeSum = $latestSubmissions->sum(function ($submission) {
            return (int) ($submission->grade ?? 0);
        });
        $averageGrade = $totalAvailableQuests > 0
            ? round($gradeSum / $totalAvailableQuests, 1)
            : 0;

        // "Completed" tetap dihitung dari quest yang submission terbarunya sudah diverifikasi.
        $totalCompleted = $latestSubmissions
            ->whereIn('status', ['Approved', 'Rejected'])
            ->count();

        $userData = [
            'id'            => $user->id,
            'uuid'          => $user->uuid,
            'name'          => $user->name,
            'username'      => $user->username,      // [TAMBAHAN] Kirim Username
            'profile_photo' => $user->profile_photo, // [TAMBAHAN] Kirim Path Foto
            'email'         => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'job_id'        => $user->job_id,
            'job_name'      => $user->job?->name,
            'job_emblem_path' => $user->job?->emblem_path,
            'gold'          => $user->gold ?? 0,
            'lvl'           => $user->level ?? $user->lvl ?? 1,
            'exp'           => $user->exp ?? 0,
            'role'          => $user->role,
        ];

        $userQuests = Submission::where('user_id', $user->id)
            ->with('quest')
            ->orderBy('created_at', 'desc')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($submission) {
                return [
                    'id'           => $submission->id,
                    'uuid'         => $submission->uuid,
                    'title'        => $submission->quest?->title ?? 'Unknown Quest',
                    'status'       => $submission->status,
                    'grade'        => $submission->grade,
                    'submitted_at' => $submission->created_at->diffForHumans(),
                    'quest_uuid'   => $submission->quest?->uuid
                ];
            });

        return Inertia::render('Profile/Edit', [
            'user'            => $userData,
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            'status'          => session('status'),
            'userQuests'      => $userQuests,
            'jobs'            => JobRole::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'averageGrade'    => $averageGrade,
            'totalCompleted'  => $totalCompleted,
        ]);
    }
    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $requestedJobId = (int) ($request->validated('job_id') ?? 0);
        $currentJobId = (int) ($user->job_id ?? 0);

        if ($requestedJobId > 0 && $requestedJobId !== $currentJobId) {
            $hasMismatchedGroups = $user->studyGroups()
                ->where('study_groups.job_id', '!=', $requestedJobId)
                ->exists();

            if ($hasMismatchedGroups) {
                return Redirect::back()->withErrors([
                    'job_id' => 'JOB_CONFLICT: Leave groups with different job path first before changing your job.',
                ]);
            }
        }

        // 1. Isi data teks (name, email, username) dari hasil validasi
        $user->fill($request->validated());

        // 2. Reset verifikasi email jika email diubah
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // 3. Handle Update Foto Profil (Artifact Update)
        if ($request->hasFile('profile_photo')) {
            // Hapus foto lama dari storage agar tidak menumpuk (sampah data)
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Simpan foto baru ke folder 'profiles' di disk 'public'
            $path = $request->file('profile_photo')->store('profiles', 'public');

            // Simpan path-nya ke kolom profile_photo di database
            $user->profile_photo = $path;
        }

        // 4. Eksekusi simpan ke database
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
