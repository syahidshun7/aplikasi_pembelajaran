<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Creation;
use App\Models\ProfileSkin;
use App\Models\CreationAppreciation;
use App\Models\CreationCollaborator;
use App\Models\CreationPhoto;
use App\Models\JobRole;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Support\Cache\CacheVersion;
use App\Services\LevelingService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $questStats = $this->resolveQuestStats($user);

        $ownedSkins = $this->resolveOwnedSkins($user);

        return Inertia::render('Profile/Edit', [
            'user'            => $this->buildUserPayload($user),
            'userQuests'      => $this->resolveUserQuests($user),
            'averageGrade'    => (float) ($questStats['average_grade'] ?? 0),
            'totalCompleted'  => (int) ($questStats['total_completed'] ?? 0),
            'classAverages'   => $questStats['class_averages'] ?? [],
            'profileView'     => 'dashboard',
            'ownedSkins'      => $ownedSkins,
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
            'jobs'            => JobRole::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'profileView'     => 'settings',
        ]);
    }

    /**
     * Display account settings inside the staff console.
     */
    public function adminEdit(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing([
            'job:id,name',
            'detailUser:id,user_id,bio,experience,location,skills',
        ]);
        $studyGroups = $user->isAdmin()
            ? collect()
            : $user->staffStudyGroups()
                ->with('job:id,name')
                ->orderBy('name')
                ->get(['study_groups.id', 'study_groups.uuid', 'study_groups.name', 'study_groups.job_id'])
                ->map(fn ($group) => [
                    'uuid' => (string) $group->uuid,
                    'name' => (string) $group->name,
                    'job' => (string) ($group->job?->name ?? ''),
                    'role' => (string) ($group->pivot?->role_in_group ?? $user->role),
                ])
                ->values();

        return Inertia::render('Admin/Profile/Edit', [
            'user' => [
                'name' => (string) $user->name,
                'username' => (string) ($user->username ?? ''),
                'email' => (string) $user->email,
                'email_verified_at' => $user->email_verified_at,
                'profile_photo' => (string) ($user->profile_photo ?? ''),
                'role' => (string) $user->role,
                'job' => (string) ($user->job?->name ?? ''),
                'bio' => (string) ($user->detailUser?->bio ?? ''),
                'experience' => (string) ($user->detailUser?->experience ?? ''),
                'location' => (string) ($user->detailUser?->location ?? ''),
                'skills' => $user->detailUser?->skills ?? [],
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'studyGroups' => $studyGroups,
            'hasGlobalAccess' => $user->isAdmin(),
        ]);
    }

    /**
     * Display a public-style profile for another user.
     */
    public function show(Request $request, User $user): Response
    {
        $questStats = $this->resolveQuestStats($user);

        return Inertia::render('Profile/Show', [
            'user' => $this->buildUserPayload($user),
            'averageGrade' => (float) ($questStats['average_grade'] ?? 0),
            'totalCompleted' => (int) ($questStats['total_completed'] ?? 0),
            'classAverages' => $questStats['class_averages'] ?? [],
            'creations' => $this->resolvePublicCreations($user, (int) ($request->user()?->id ?? 0)),
            'creationStats' => [
                'total_public' => $this->resolveTotalPublicCreations($user),
                'total_appreciations_received' => $this->resolveTotalCreationAppreciationsReceived($user),
            ],
            'activeSkin' => $user->activeProfileSkin ? $user->activeProfileSkin->toThemeArray() : null,
        ]);
    }

    public function transferRecipients(Request $request)
    {
        $user = $request->user();

        if ($user->isStaffPlayMode()) {
            return response()->json(['data' => []]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
        ]);

        $queryText = ltrim(trim((string) ($validated['q'] ?? '')), '@');

        if (mb_strlen($queryText) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::query()
            ->whereKeyNot($user->id)
            ->whereNull('deleted_at')
            ->whereNotIn('role', User::staffRoles())
            ->where(function ($query) use ($queryText) {
                $query->where('username', 'like', "{$queryText}%")
                    ->orWhere('username', 'like', "%{$queryText}%");
            })
            ->orderByRaw("CASE WHEN username LIKE ? THEN 0 ELSE 1 END", ["{$queryText}%"])
            ->orderBy('username')
            ->limit(8)
            ->get(['id', 'name', 'username'])
            ->map(fn (User $recipient) => [
                'id' => (int) $recipient->id,
                'name' => (string) ($recipient->name ?? ''),
                'username' => (string) ($recipient->username ?? ''),
            ])
            ->values();

        return response()->json(['data' => $users]);
    }
    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $profilePayload = collect($validated)
            ->except('profile_photo')
            ->all();
        $requestedJobId = (int) ($validated['job_id'] ?? 0);
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
        $user->fill($profilePayload);

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

        $bio = isset($validated['bio']) ? trim((string) $validated['bio']) : null;
        $experience = isset($validated['experience']) ? trim((string) $validated['experience']) : null;
        $location = isset($validated['location']) ? trim((string) $validated['location']) : null;
        $skillsRaw = isset($validated['skills_text']) ? trim((string) $validated['skills_text']) : '';

        $bio = $bio !== '' ? $bio : null;
        $experience = $experience !== '' ? $experience : null;
        $location = $location !== '' ? $location : null;

        $skills = [];
        if ($skillsRaw !== '') {
            $skills = collect(explode(',', $skillsRaw))
                ->map(fn ($skill) => trim((string) $skill))
                ->filter(fn ($skill) => $skill !== '')
                ->unique()
                ->values()
                ->all();
        }

        $detailPayload = [
            'bio' => $bio,
            'experience' => $experience,
            'location' => $location,
            'skills' => !empty($skills) ? $skills : null,
        ];

        $hasDetailValues = collect($detailPayload)->contains(function ($value) {
            if (is_array($value)) {
                return count($value) > 0;
            }

            return !is_null($value) && $value !== '';
        });

        if ($hasDetailValues) {
            $user->detailUser()->updateOrCreate(['user_id' => $user->id], $detailPayload);
        } elseif ($user->detailUser) {
            $user->detailUser()->delete();
        }

        // 4. Eksekusi simpan ke database
        $user->save();

        CacheVersion::bump('home');
        CacheVersion::bump('hall_of_creations');

        if ($user->hasRole(User::ROLE_MENTOR)) {
            CacheVersion::bump('landing');
        }

        $redirectRoute = $request->routeIs('admin.profile.update')
            ? 'admin.profile.edit'
            : 'profile.edit';

        return Redirect::route($redirectRoute)->with('status', 'profile-updated');
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
        $user->loadMissing([
            'job:id,name,emblem_path',
            'detailUser:id,user_id,bio,experience,location,skills',
            'activeProfileSkin',
        ]);

        $totalExp = (int) ($user->exp ?? 0);
        $progress = LevelingService::progress($totalExp);

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
            'lvl'           => $progress['level'],
            'exp'           => $totalExp,
            'level_progress' => $progress,
            'role'          => $user->role,
            'staff_play_mode' => $user->isStaffPlayMode(),
            'bio'           => $user->detailUser?->bio,
            'experience'    => $user->detailUser?->experience,
            'location'      => $user->detailUser?->location,
            'skills'        => $user->detailUser?->skills,
            'active_skin'   => $user->activeProfileSkin ? $user->activeProfileSkin->toThemeArray() : null,
        ];
    }

    private function resolveTransferUsers(User $user)
    {
        if ($user->isStaffPlayMode()) {
            return collect();
        }

        return User::query()
            ->whereKeyNot($user->id)
            ->whereNull('deleted_at')
            ->whereNotIn('role', User::staffRoles())
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'username', 'role']);
    }

    private function resolveQuestStats($user): array
    {
        $groupRows = DB::table('group_user')
            ->join('study_groups', 'study_groups.id', '=', 'group_user.study_group_id')
            ->where('group_user.user_id', (int) $user->id)
            ->whereNull('group_user.deleted_at')
            ->whereNull('study_groups.deleted_at')
            ->select('study_groups.id', 'study_groups.name')
            ->distinct()
            ->get();

        $userGroupIds = $groupRows
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $groupNamesById = $groupRows
            ->mapWithKeys(fn ($row) => [(int) $row->id => (string) $row->name])
            ->all();

        $availableQuests = Quest::query()
            ->where(function ($query) use ($userGroupIds) {
                $query->whereNull('study_group_id');

                if (!empty($userGroupIds)) {
                    $query->orWhereIn('study_group_id', $userGroupIds);
                }
            })
            ->publishedForAverage()
            ->get(['id', 'study_group_id']);

        $totalAvailableQuests = (int) $availableQuests->count();
        $availableQuestIds = $availableQuests
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $questCountByGroup = [];
        foreach ($availableQuests as $quest) {
            $groupKey = is_null($quest->study_group_id) ? 0 : (int) $quest->study_group_id;
            $questCountByGroup[$groupKey] = (int) ($questCountByGroup[$groupKey] ?? 0) + 1;
        }

        $latestSubmissions = empty($availableQuestIds)
            ? collect()
            : Submission::query()
                ->joinSub(
                    Submission::query()
                        ->where('user_id', (int) $user->id)
                        ->whereIn('quest_id', $availableQuestIds)
                        ->selectRaw('MAX(id) as id')
                        ->groupBy('quest_id'),
                    'latest',
                    fn ($join) => $join->on('submissions.id', '=', 'latest.id')
                )
                ->leftJoin('quests', 'quests.id', '=', 'submissions.quest_id')
                ->get(['submissions.grade', 'submissions.status', 'quests.study_group_id']);

        $gradeSum = 0;
        $gradeSumByGroup = [];
        $completedCountByGroup = [];

        foreach ($latestSubmissions as $submission) {
            $grade = (int) ($submission->grade ?? 0);
            $status = (string) ($submission->status ?? '');
            $groupKey = is_null($submission->study_group_id) ? 0 : (int) $submission->study_group_id;

            $gradeSum += $grade;
            $gradeSumByGroup[$groupKey] = (int) ($gradeSumByGroup[$groupKey] ?? 0) + $grade;

            if (in_array($status, ['Approved', 'Rejected'], true)) {
                $completedCountByGroup[$groupKey] = (int) ($completedCountByGroup[$groupKey] ?? 0) + 1;
            }
        }

        $averageGrade = $totalAvailableQuests > 0
            ? round($gradeSum / $totalAvailableQuests, 1)
            : 0;

        $totalCompleted = (int) array_sum($completedCountByGroup);

        $classAverages = collect($questCountByGroup)
            ->map(function (int $totalQuests, int $groupKey) use ($groupNamesById, $gradeSumByGroup, $completedCountByGroup) {
                if ($totalQuests <= 0) {
                    return null;
                }

                $isGeneralClass = $groupKey === 0;
                $className = $isGeneralClass
                    ? 'General'
                    : (string) ($groupNamesById[$groupKey] ?? "Class {$groupKey}");

                return [
                    'study_group_id' => $isGeneralClass ? null : $groupKey,
                    'class_name' => $className,
                    'average_grade' => round(((int) ($gradeSumByGroup[$groupKey] ?? 0)) / $totalQuests, 1),
                    'total_quests' => $totalQuests,
                    'completed_quests' => (int) ($completedCountByGroup[$groupKey] ?? 0),
                    'is_general' => $isGeneralClass,
                ];
            })
            ->filter()
            ->sortBy([
                ['is_general', 'asc'],
                ['class_name', 'asc'],
            ])
            ->values()
            ->map(fn (array $item) => [
                'study_group_id' => $item['study_group_id'],
                'class_name' => $item['class_name'],
                'average_grade' => $item['average_grade'],
                'total_quests' => $item['total_quests'],
                'completed_quests' => $item['completed_quests'],
            ])
            ->all();

        return [
            'average_grade' => $averageGrade,
            'total_completed' => $totalCompleted,
            'class_averages' => $classAverages,
        ];
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

    private function resolvePublicCreations(User $user, int $viewerId): array
    {
        $creations = Creation::query()
            ->publicVisible()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $user->id));
            })
            ->with([
                'user:id,name,username,profile_photo',
                'photos:id,creation_id,path,sort_order',
                'collaborators.user:id,name,username,profile_photo',
            ])
            ->withCount(['appreciations', 'insights', 'photos', 'collaborators'])
            ->orderByDesc('appreciations_count')
            ->orderByDesc('insights_count')
            ->latest()
            ->take(12)
            ->get();

        $appreciatedIds = $viewerId > 0 && $creations->isNotEmpty()
            ? CreationAppreciation::query()
                ->where('user_id', $viewerId)
                ->whereIn('creation_id', $creations->pluck('id')->all())
                ->pluck('creation_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        return $creations
            ->map(function (Creation $creation) use ($appreciatedIds, $user) {
                return [
                    'id' => (int) $creation->id,
                    'slug' => (string) ($creation->slug ?? ''),
                    'url' => route('hall.creations.show', ['creation' => $creation->slug ?: $creation->id], false),
                    'user_id' => (int) $creation->user_id,
                    'title' => (string) $creation->title,
                    'description' => (string) $creation->description,
                    'content' => (string) ($creation->content ?? ''),
                    'link' => (string) ($creation->link ?? ''),
                    'category' => $creation->category ? (string) $creation->category : null,
                    'category_id' => $creation->category_id ? (int) $creation->category_id : null,
                    'tags' => collect($creation->tags ?? [])->map(fn ($tag) => (string) $tag)->values()->all(),
                    'featured_image' => (string) ($creation->featured_image ?? ''),
                    'publication_status' => (string) ($creation->publication_status ?? ((bool) $creation->is_public ? 'publish' : 'draft')),
                    'status' => (string) $creation->status,
                    'progress' => (int) ($creation->progress ?? 0),
                    'is_public' => (bool) $creation->is_public,
                    'is_open_for_collaboration' => (bool) $creation->is_open_for_collaboration,
                    'appreciations_count' => (int) ($creation->appreciations_count ?? 0),
                    'insights_count' => (int) ($creation->insights_count ?? 0),
                    'photos_count' => (int) ($creation->photos_count ?? $creation->photos->count()),
                    'collaborators_count' => (int) ($creation->collaborators_count ?? $creation->collaborators->count()),
                    'thumbnail_url' => $this->normalizePublicAssetUrl((string) ($creation->photos->first()?->url ?? ($creation->featured_image ?? ''))),
                    'photos' => $creation->photos
                        ->map(fn (CreationPhoto $photo) => [
                            'id' => (int) $photo->id,
                            'url' => (string) $photo->url,
                            'sort_order' => (int) ($photo->sort_order ?? 0),
                        ])
                        ->values()
                        ->all(),
                    'creator' => [
                        'id' => (int) ($creation->user?->id ?? 0),
                        'name' => (string) ($creation->user?->name ?? ''),
                        'username' => (string) ($creation->user?->username ?? ''),
                            'profile_photo' => (string) ($creation->user?->profile_photo ?? ''),
                        ],
                    'team' => [
                        [
                            'id' => (int) ($creation->user?->id ?? 0),
                            'name' => (string) ($creation->user?->name ?? ''),
                            'username' => (string) ($creation->user?->username ?? ''),
                            'profile_photo' => (string) ($creation->user?->profile_photo ?? ''),
                            'role' => Creation::COLLABORATOR_ROLE_OWNER,
                            'is_owner' => true,
                        ],
                        ...$creation->collaborators->map(fn (CreationCollaborator $member) => [
                            'id' => (int) ($member->user?->id ?? 0),
                            'name' => (string) ($member->user?->name ?? ''),
                            'username' => (string) ($member->user?->username ?? ''),
                            'profile_photo' => (string) ($member->user?->profile_photo ?? ''),
                            'role' => (string) $member->role,
                            'is_owner' => false,
                        ])->values()->all(),
                    ],
                    'team_size' => (int) (1 + $creation->collaborators->count()),
                    'ownership_type' => (int) $creation->user_id === (int) $user->id ? 'owner' : 'collaborator',
                    'created_at' => $creation->created_at?->toISOString(),
                    'updated_at' => $creation->updated_at?->toISOString(),
                    'is_appreciated' => in_array((int) $creation->id, $appreciatedIds, true),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveTotalPublicCreations(User $user): int
    {
        return (int) Creation::query()
            ->publicVisible()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $user->id));
            })
            ->count();
    }

    private function normalizePublicAssetUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    private function resolveTotalCreationAppreciationsReceived(User $user): int
    {
        return (int) CreationAppreciation::query()
            ->whereHas('creation', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $user->id));
            })
            ->count();
    }

    private function resolveOwnedSkins(User $user): array
    {
        if (! $user->active_profile_skin_id) {
            return [];
        }

        $ownedShopItemIds = $user->inventories()
            ->where('quantity', '>=', 1)
            ->pluck('shop_item_id')
            ->all();

        return ProfileSkin::query()
            ->whereIn('shop_item_id', $ownedShopItemIds)
            ->whereKey((int) $user->active_profile_skin_id)
            ->where('is_active', true)
            ->get()
            ->map(fn ($skin) => array_merge($skin->toThemeArray(), [
                'is_active' => (int) $user->active_profile_skin_id === (int) $skin->id,
            ]))
            ->values()
            ->all();
    }
}
