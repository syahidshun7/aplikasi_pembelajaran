<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Models\JobRole;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:all,admin,mentor,user,student'],
            'rank_by' => ['nullable', 'in:newest,highest_gold,highest_exp,highest_grade'],
            'grade_order' => ['nullable', 'in:none,asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $role = (string) ($validated['role'] ?? 'all');
        $rankBy = (string) ($validated['rank_by'] ?? 'newest');
        $gradeOrder = (string) ($validated['grade_order'] ?? 'none');
        if ($rankBy === 'highest_grade' && $gradeOrder === 'none') {
            // Backward compatibility for old query params.
            $gradeOrder = 'desc';
        }

        $levelColumn = Schema::hasColumn('users', 'lvl') ? 'lvl' : 'level';

        $users = User::query()
            ->with([
                'detailUser:id,user_id,bio,experience,location,skills',
                'job:id,name',
            ])
            ->withCount('submissions')
            ->withMax('submissions as highest_grade', 'grade')
            ->when($role !== '' && $role !== 'all', function ($query) use ($role) {
                $query->where('role', $role);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($gradeOrder === 'desc', function ($query) {
                $query->orderByRaw('COALESCE(highest_grade, 0) DESC')
                    ->orderByDesc('exp')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'asc', function ($query) {
                $query->orderByRaw('COALESCE(highest_grade, 0) ASC')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'none' && $rankBy === 'highest_gold', function ($query) {
                $query->orderByDesc('gold')
                    ->orderByDesc('exp')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'none' && $rankBy === 'highest_exp', function ($query) {
                $query->orderByDesc('exp')
                    ->orderByDesc('gold')
                    ->orderByDesc('created_at');
            })
            ->when($gradeOrder === 'none' && $rankBy === 'newest', function ($query) {
                $query->orderByDesc('created_at');
            })
            ->paginate(10, [
                'id',
                'name',
                'username',
                'email',
                'role',
                'job_id',
                'profile_photo',
                'gold',
                'exp',
                $levelColumn,
                'created_at',
            ])
            ->withQueryString();

        $pageUsers = $users->getCollection();
        $userIds = $pageUsers->pluck('id')->all();

        if (!empty($userIds)) {
            $allQuests = Quest::query()->get(['id', 'study_group_id']);

            $publicQuestIds = [];
            $groupQuestIdsByGroup = [];
            foreach ($allQuests as $quest) {
                if (is_null($quest->study_group_id)) {
                    $publicQuestIds[] = (int) $quest->id;
                } else {
                    $groupId = (int) $quest->study_group_id;
                    $groupQuestIdsByGroup[$groupId][] = (int) $quest->id;
                }
            }

            $userGroupIdsMap = DB::table('group_user')
                ->whereIn('user_id', $userIds)
                ->select('user_id', 'study_group_id')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->pluck('study_group_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

            $latestSubmissions = Submission::query()
                ->whereIn('user_id', $userIds)
                ->orderByDesc('id')
                ->get(['user_id', 'quest_id', 'grade']);

            $latestGradeByUserQuest = [];
            foreach ($latestSubmissions as $submission) {
                $uid = (int) $submission->user_id;
                $qid = (int) $submission->quest_id;

                if (!isset($latestGradeByUserQuest[$uid][$qid])) {
                    $latestGradeByUserQuest[$uid][$qid] = (int) ($submission->grade ?? 0);
                }
            }

            $pageUsers->transform(function ($user) use ($publicQuestIds, $groupQuestIdsByGroup, $userGroupIdsMap, $latestGradeByUserQuest) {
                $uid = (int) $user->id;

                $availableQuestIds = $publicQuestIds;
                $userGroupIds = $userGroupIdsMap->get($uid, []);
                foreach ($userGroupIds as $groupId) {
                    if (isset($groupQuestIdsByGroup[$groupId])) {
                        $availableQuestIds = array_merge($availableQuestIds, $groupQuestIdsByGroup[$groupId]);
                    }
                }

                $availableQuestIds = array_values(array_unique($availableQuestIds));
                $totalAvailableQuests = count($availableQuestIds);

                $gradeSum = 0;
                $userLatestGrades = $latestGradeByUserQuest[$uid] ?? [];
                foreach ($availableQuestIds as $questId) {
                    $gradeSum += (int) ($userLatestGrades[$questId] ?? 0);
                }

                $user->avg_grade = $totalAvailableQuests > 0
                    ? round($gradeSum / $totalAvailableQuests, 1)
                    : 0;
                $user->level_display = (int) ($user->lvl ?? $user->level ?? 1);
                $user->highest_grade = (int) ($user->highest_grade ?? 0);

                return $user;
            });

            $users->setCollection($pageUsers);
        }

        return Inertia::render('Users/Admin/Index', [
            'users' => $users,
            'availableRoles' => User::assignableRoles(),
            'jobRoles' => JobRole::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'rank_by' => $rankBy,
                'grade_order' => $gradeOrder,
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,mentor,user,student'],
        ]);

        // Prevent an admin from accidentally removing their own admin access.
        if ((int) $request->user()->id === (int) $user->id && $validated['role'] !== 'admin') {
            return back()->withErrors([
                'role' => 'Kamu tidak bisa menurunkan role akun admin yang sedang login.',
            ]);
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return back()->with('message', 'USER_ROLE_UPDATED');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return back()->with('message', 'USER_PASSWORD_RESET_SUCCESS');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $previousJobId = $user->job_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', 'in:admin,mentor,user,student'],
            'job_id' => ['nullable', 'integer', 'exists:job_roles,id'],
            'gold' => ['required', 'integer', 'min:0'],
            'exp' => ['required', 'integer', 'min:0'],
            'level' => ['required', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'bio' => ['nullable', 'string', 'max:1200'],
            'experience' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'skills_text' => ['nullable', 'string', 'max:500'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        if ((int) $request->user()->id === (int) $user->id && $validated['role'] !== 'admin') {
            return back()->withErrors([
                'role' => 'Kamu tidak bisa menurunkan role akun admin yang sedang login.',
            ]);
        }

        $payload = [
            'name' => trim((string) $validated['name']),
            'username' => trim((string) ($validated['username'] ?? '')) ?: null,
            'email' => strtolower(trim((string) $validated['email'])),
            'role' => $validated['role'],
            'job_id' => $validated['job_id'] ?? null,
            'gold' => (int) $validated['gold'],
            'exp' => (int) $validated['exp'],
        ];

        if (Schema::hasColumn('users', 'lvl')) {
            $payload['lvl'] = (int) $validated['level'];
        }
        if (Schema::hasColumn('users', 'level')) {
            $payload['level'] = (int) $validated['level'];
        }
        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make((string) $validated['password']);
        }

        $user->forceFill($payload)->save();

        $avatarUpdated = false;
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $path = $request->file('profile_photo')->store('profiles', 'public');
            $user->profile_photo = $path;
            $user->save();
            $avatarUpdated = true;
        } elseif (!empty($validated['remove_avatar'])) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $user->profile_photo = null;
            $user->save();
            $avatarUpdated = true;
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

        $jobChanged = (int) ($previousJobId ?? 0) !== (int) ($user->job_id ?? 0);

        if ($validated['role'] === User::ROLE_MENTOR || $user->detailUser || $hasDetailValues || $avatarUpdated || $jobChanged) {
            CacheVersion::bump('landing');
        }

        return back()->with('message', 'USER_DATA_UPDATED');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors([
                'user' => 'Kamu tidak bisa menghapus akun admin yang sedang login.',
            ]);
        }

        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        return back()->with('message', 'USER_ACCOUNT_DELETED');
    }
}
