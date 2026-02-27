<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:all,admin,user,student'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $role = (string) ($validated['role'] ?? 'all');

        $users = User::query()
            ->withCount('submissions')
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
            ->orderByDesc('created_at')
            ->paginate(10, [
                'id',
                'name',
                'username',
                'email',
                'role',
                'gold',
                'exp',
                'level',
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

                return $user;
            });

            $users->setCollection($pageUsers);
        }

        return Inertia::render('Users/Admin/Index', [
            'users' => $users,
            'availableRoles' => ['admin', 'user', 'student'],
            'filters' => [
                'search' => $search,
                'role' => $role,
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,user,student'],
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
}
