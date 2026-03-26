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
     * Display the user's profile dashboard.
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        [$averageGrade, $totalCompleted] = $this->resolveQuestStats($user);

        return Inertia::render('Profile/Edit', [
            'user'            => $this->buildUserPayload($user),
            'userQuests'      => $this->resolveUserQuests($user),
            'averageGrade'    => $averageGrade,
            'totalCompleted'  => $totalCompleted,
            'profileView'     => 'dashboard',
        ]);
    }

    /**
     * Display the user's profile edit form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'user'            => $this->buildUserPayload($user),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status'          => session('status'),
            'jobs'            => JobRole::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'profileView'     => 'settings',
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

    private function buildUserPayload($user): array
    {
        $user->loadMissing('job:id,name,emblem_path');

        return [
            'id'            => $user->id,
            'uuid'          => $user->uuid,
            'name'          => $user->name,
            'username'      => $user->username,
            'profile_photo' => $user->profile_photo,
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
    }

    private function resolveQuestStats($user): array
    {
        $userGroupIds = $user->studyGroups()->pluck('study_groups.id')->toArray();

        $availableQuestsQuery = Quest::query()
            ->where(function ($query) use ($userGroupIds) {
                $query->whereNull('study_group_id')
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
            ->select('id');

        $totalAvailableQuests = (int) (clone $availableQuestsQuery)->count();

        $latestSubmissions = Submission::query()
            ->joinSub(
                Submission::query()
                    ->where('user_id', $user->id)
                    ->whereIn('quest_id', $availableQuestsQuery)
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('quest_id'),
                'latest',
                fn ($join) => $join->on('submissions.id', '=', 'latest.id')
            )
            ->get(['submissions.quest_id', 'submissions.grade', 'submissions.status']);

        $gradeSum = (int) $latestSubmissions->sum(fn ($submission) => (int) ($submission->grade ?? 0));
        $averageGrade = $totalAvailableQuests > 0
            ? round($gradeSum / $totalAvailableQuests, 1)
            : 0;

        $totalCompleted = (int) $latestSubmissions
            ->filter(fn ($submission) => in_array((string) ($submission->status ?? ''), ['Approved', 'Rejected'], true))
            ->count();

        return [$averageGrade, $totalCompleted];
    }

    private function resolveUserQuests($user)
    {
        return Submission::where('user_id', $user->id)
            ->with('quest:id,uuid,title')
            ->select([
                'id',
                'uuid',
                'user_id',
                'quest_id',
                'status',
                'grade',
                'created_at',
            ])
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
                    'quest_uuid'   => $submission->quest?->uuid,
                ];
            });
    }
}
