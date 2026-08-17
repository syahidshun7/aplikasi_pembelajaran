<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Services\DailyQuestService;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use App\Models\Guide; // Ganti dengan nama model materimu jika berbeda
use App\Models\JobRole;
use App\Models\Event;
use App\Models\DoopNewsPost;
use App\Models\UserContentRead;
use App\Models\UserQuestUnlock;
use App\Services\LevelingService;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;

class HomeController extends Controller
{
   public function index(Request $request, DailyQuestService $dailyQuestService)
{
    if (!Auth::check()) {
        return $this->renderLanding();
    }

    if (Auth::user()?->isStaff()) {
        return redirect()->route('dashboard');
    }

    $userId = Auth::id();
    $user = Auth::user();
    $userJobId = $user?->job_id;
    $canManageMembership = $user && ! $user->isStaff();
    if ($user) {
        $user->loadMissing('job:id,name');
    }

    $userClassGroups = $userId && $canManageMembership
        ? $user->studyGroups()
            ->select('study_groups.id', 'study_groups.name')
            ->orderBy('study_groups.name')
            ->get()
            ->map(fn ($group) => [
                'id' => (int) $group->id,
                'name' => (string) $group->name,
            ])
            ->values()
        : collect();

    $userClassGroupIds = $userClassGroups
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $defaultClassGroup = $userClassGroups->first();
    $defaultClassGroupId = (int) ($defaultClassGroup['id'] ?? 0);
    $defaultClassGroupName = (string) ($defaultClassGroup['name'] ?? '');
    $requestedClassGroupIdFromHeader = max(0, (int) $request->header('X-Leaderboard-Class-Group-Id', 0));
    $requestedClassGroupId = $requestedClassGroupIdFromHeader > 0
        ? $requestedClassGroupIdFromHeader
        : max(0, (int) $request->integer('leaderboard_class_group_id'));
    $activeClassGroupId = in_array($requestedClassGroupId, $userClassGroupIds, true) ? $requestedClassGroupId : 0;
    $activeClassGroup = $userClassGroups->firstWhere('id', $activeClassGroupId);
    $activeClassGroupName = (string) ($activeClassGroup['name'] ?? '');
    $selectedClassGroupId = $activeClassGroupId > 0 ? $activeClassGroupId : $defaultClassGroupId;
    $selectedClassGroup = $userClassGroups->firstWhere('id', $selectedClassGroupId);
    $selectedClassGroupName = (string) ($selectedClassGroup['name'] ?? '');

    // 1. Ambil Quest dengan status submission (Logika Kelompok Party)
    $userGroupIds = $userId && $canManageMembership
        ? $user->studyGroups()
            ->where('study_groups.job_id', $userJobId)
            ->pluck('study_groups.id')
            ->toArray()
        : [];

    $homeCacheVersion = CacheVersion::get('home');
    $jobKey = is_null($userJobId) ? 'none' : (string) (int) $userJobId;
    $groupKey = sha1(json_encode(collect($userGroupIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all()));
    $classGroupKey = $activeClassGroupId > 0 ? (string) $activeClassGroupId : 'none';
    $leaderboardMeta = [
        'global_scope_label' => trim((string) ($user->job?->name ?? 'Unassigned Job')),
        'class_scope_label' => $selectedClassGroupName !== '' ? $selectedClassGroupName : 'Belum Join Kelas',
        'class_groups' => $userClassGroups->all(),
        'default_class_group_id' => $defaultClassGroupId > 0 ? $defaultClassGroupId : null,
        'default_class_group_name' => $defaultClassGroupName !== '' ? $defaultClassGroupName : null,
        'selected_class_group_id' => $selectedClassGroupId > 0 ? $selectedClassGroupId : null,
        'selected_class_group_name' => $selectedClassGroupName !== '' ? $selectedClassGroupName : null,
        'loaded_class_group_id' => $activeClassGroupId > 0 ? $activeClassGroupId : null,
        'loaded_class_group_name' => $activeClassGroupName !== '' ? $activeClassGroupName : null,
        'active_class_group_id' => $activeClassGroupId > 0 ? $activeClassGroupId : null,
        'active_class_group_name' => $activeClassGroupName !== '' ? $activeClassGroupName : null,
    ];

    if ($this->isLeaderboardOnlyPartialRequest($request)) {
        $leaderboardData = $this->resolveLeaderboardData(
            $homeCacheVersion,
            $jobKey,
            $classGroupKey,
            $userJobId,
            $activeClassGroupId
        );
        $globalPlayers = $leaderboardData['globalPlayers'];
        $classPlayers = $leaderboardData['classPlayers'];

        return Inertia::render('home', [
            'leaderboards' => [
                'global' => $globalPlayers,
                'class' => $classPlayers,
                // Backward compatibility for older clients still reading legacy keys.
                'job' => $globalPlayers,
                'overall' => $globalPlayers,
                'party' => $classPlayers,
            ],
            'leaderboardMeta' => $leaderboardMeta,
        ]);
    }

    $partialKeys = collect();
    $shouldLoadAll = true;
    $shouldLoadQuests = true;
    $shouldLoadGuides = true;
    $shouldLoadLeaderboard = true;
    $shouldLoadStudyGroups = true;
    $shouldLoadEvents = true;
    $shouldLoadDoopNews = true;
    $shouldLoadDailyQuestBoard = true;
    $shouldLoadNewContentCounts = true;

    if ($shouldLoadQuests) {
        $quests = Cache::remember(
        "home.quests.v{$homeCacheVersion}.with_group_v2.job.{$jobKey}.groups.{$groupKey}",
        now()->addMinutes(5),
        fn () => Quest::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                ->orWhereIn('study_group_id', $userGroupIds);
        })
            ->with('studyGroup:id,name')
            ->listedForUsers()
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($quest) => $quest->toArray())
        );

        $questIds = $quests->pluck('id')->map(fn ($id) => (int) $id)->all();

        $submissionStatusesByQuest = [];
        $submittedQuestIdSet = [];
        if (! empty($questIds)) {
            $latestSubmissions = Submission::query()
                ->joinSub(
                    Submission::query()
                        ->where('user_id', $userId)
                        ->whereIn('quest_id', $questIds)
                        ->selectRaw('MAX(id) as id')
                        ->groupBy('quest_id'),
                    'latest',
                    fn ($join) => $join->on('submissions.id', '=', 'latest.id')
                )
                ->get(['submissions.quest_id', 'submissions.status']);

            $submissionStatusesByQuest = $latestSubmissions
                ->pluck('status', 'quest_id')
                ->mapWithKeys(fn ($status, $questId) => [(int) $questId => $status])
                ->all();

            $submittedQuestIdSet = array_fill_keys(array_keys($submissionStatusesByQuest), true);
        }

        $unlockedQuestIdSet = [];
        if (! empty($questIds)) {
            $unlockedQuestIds = UserQuestUnlock::query()
                ->where('user_id', $userId)
                ->whereIn('quest_id', $questIds)
                ->pluck('quest_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $unlockedQuestIdSet = array_fill_keys($unlockedQuestIds, true);
        }

        $seenQuestIdSet = $this->seenQuestIdSet($userId, $questIds);

        $questFeed = $quests->map(function ($quest) use ($submittedQuestIdSet, $submissionStatusesByQuest, $unlockedQuestIdSet, $seenQuestIdSet) {
            $questId = (int) ($quest['id'] ?? 0);
            $quest['user_has_submitted'] = isset($submittedQuestIdSet[$questId]);
            $quest['user_submission_status'] = $submissionStatusesByQuest[$questId] ?? null;
            $quest['user_has_unlock'] = isset($unlockedQuestIdSet[$questId]);
            $quest['is_new_for_user'] = $this->isQuestNewForUser($quest, $seenQuestIdSet);
            return $quest;
        });

        $quests = $questFeed
            ->sortBy(fn ($quest) => $this->questFeedSortTuple($quest))
            ->take(10)
            ->values();
    }

    // 2. Ambil Data Materi / Guide (Global + sesuai study group user)
    if ($shouldLoadGuides) {
        $materi = Cache::remember(
        "home.guides.v{$homeCacheVersion}.job.{$jobKey}.groups.{$groupKey}",
        now()->addMinutes(5),
        fn () => Guide::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                ->orWhereIn('study_group_id', $userGroupIds);
        })
            ->with('studyGroup:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($guide) => $guide->toArray())
        );
        $guideIds = $materi->pluck('id')->map(fn ($id) => (int) $id)->all();
        $seenGuideIdSet = $this->seenGuideIdSet($userId, $guideIds);
        $materi = $materi->map(function ($guide) use ($seenGuideIdSet) {
            $guide['is_new_for_user'] = $this->isGuideNewForUser($guide, $seenGuideIdSet);
            return $guide;
        });
    }

    // 3. Ambil Data Leaderboard (Global berdasarkan job user + Kelas aktif user)
    if ($shouldLoadLeaderboard) {
        $leaderboardData = $this->resolveLeaderboardData(
            $homeCacheVersion,
            $jobKey,
            $classGroupKey,
            $userJobId,
            $activeClassGroupId
        );
        $globalPlayers = $leaderboardData['globalPlayers'];
        $classPlayers = $leaderboardData['classPlayers'];
    }

    // 4. Ambil Data Kelompok Belajar (Study Groups)
    $studyGroupCacheVersion = CacheVersion::get('study_groups');
    if ($shouldLoadStudyGroups) {
        $studyGroups = Cache::remember(
        "study_groups.list.v{$studyGroupCacheVersion}.job.{$jobKey}",
        now()->addMinutes(5),
        fn () => \App\Models\StudyGroup::query()
            // select dulu lalu tambahkan hitungan agar kolom users_count tidak ter-overwrite
            ->select([
                'id',
                'uuid',
                'name',
                'description',
                'max_members',
                'min_level',
                'job_id',
                'created_at',
            ])
            
            ->withCount([
                'users as users_count' => fn ($userQuery) => $userQuery->whereNotIn('users.role', User::staffRoles()),
            ])
            ->where('job_id', $userJobId)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($group) => $group->toArray())
        );

        $groupRequestStatuses = $userId && $canManageMembership
            ? StudyGroupJoinRequest::where('user_id', $userId)->pluck('status', 'study_group_id')->toArray()
            : [];

        $studyGroupIds = $studyGroups->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! $shouldLoadAll && $partialKeys->contains('studyGroups')) {
            foreach ($studyGroupIds as $studyGroupId) {
                UserContentRead::markSeen($userId, UserContentRead::TYPE_STUDY_GROUP, $studyGroupId);
            }
        }

        $seenStudyGroupIdSet = $this->seenStudyGroupIdSet($userId, $studyGroupIds);

        $studyGroups = $studyGroups->map(function ($group) use ($userGroupIds, $groupRequestStatuses, $seenStudyGroupIdSet) {
            $groupId = (int) ($group['id'] ?? 0);
            $group['is_member'] = in_array($groupId, $userGroupIds, true);
            $group['join_request_status'] = $groupRequestStatuses[$groupId] ?? null;
            $group['is_new_for_user'] = $this->isStudyGroupNewForUser($group, $seenStudyGroupIdSet);
            return $group;
        });
    }

    if ($shouldLoadEvents) {
        $events = Cache::remember(
        "home.events.v{$homeCacheVersion}.job.{$jobKey}.groups.{$groupKey}",
        now()->addMinutes(5),
        fn () => Event::query()
            ->with(['studyGroup:id,name', 'job:id,name'])
            ->withCount(['guides', 'quests'])
            ->where(function ($query) use ($userGroupIds, $userJobId) {
                $query->where(function ($publicQuery) use ($userJobId) {
                    $publicQuery->whereNull('study_group_id')
                        ->where(function ($audienceQuery) use ($userJobId) {
                            $audienceQuery->whereNull('job_id');

                            if (! is_null($userJobId)) {
                                $audienceQuery->orWhere('job_id', $userJobId);
                            }
                        });
                })
                    ->orWhereIn('study_group_id', $userGroupIds);
            })
            ->orderByDesc('created_at')
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('starts_at')
            ->orderByDesc('sequence_order')
            ->take(10)
            ->get()
            ->map(fn ($event) => $event->toArray())
        );
        $eventIds = $events->pluck('id')->map(fn ($id) => (int) $id)->all();
        $seenEventIdSet = $this->seenEventIdSet($userId, $eventIds);
        $events = $events->map(function ($event) use ($seenEventIdSet) {
            $event['is_new_for_user'] = $this->isEventNewForUser($event, $seenEventIdSet);
            return $event;
        });
    }

    if ($shouldLoadDoopNews) {
        $doopNewsPosts = Cache::remember(
            "home.doopnews.v{$homeCacheVersion}",
            now()->addMinutes(5),
            fn () => DoopNewsPost::query()
                ->published()
                ->with('author:id,name,username,role,profile_photo')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->take(5)
                ->get()
                ->map(fn ($post) => $post->toArray())
        );

        $doopNewsIds = $doopNewsPosts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $seenDoopNewsIdSet = $this->seenDoopNewsIdSet($userId, $doopNewsIds);
        $doopNewsPosts = $doopNewsPosts->map(function ($post) use ($seenDoopNewsIdSet) {
            $post['is_new_for_user'] = $this->isDoopNewsNewForUser($post, $seenDoopNewsIdSet);
            return $post;
        });

        if (! $shouldLoadAll && $partialKeys->contains('doopNewsPosts')) {
            foreach ($doopNewsIds as $doopNewsId) {
                UserContentRead::markSeen($userId, UserContentRead::TYPE_DOOP_NEWS, $doopNewsId);
            }
        }
    }

    if ($shouldLoadDailyQuestBoard) {
        $dailyQuestBoard = $dailyQuestService->buildBoardForUser($user);
    }

    if ($shouldLoadNewContentCounts) {
        $newQuestCount = $this->countNewVisibleQuests($userId, $userGroupIds);
        $newGuideCount = $this->countNewVisibleGuides($userId, $userGroupIds);
        $newEventCount = $this->countNewVisibleEvents($userId, $userGroupIds, $userJobId);
        $newStudyGroupCount = $this->countNewVisibleStudyGroups($userId, $userJobId);
        $newDoopNewsCount = $this->countNewVisibleDoopNews($userId);
    }

    return Inertia::render('home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'dailyQuestBoard' => $dailyQuestBoard,
        'quests' => $quests,
        'materi' => $materi,
        'players' => $globalPlayers,
        'leaderboards' => [
            'global' => $globalPlayers,
            'class' => $classPlayers,
            // Backward compatibility for older clients still reading legacy keys.
            'job' => $globalPlayers,
            'overall' => $globalPlayers,
            'party' => $classPlayers,
        ],
        'leaderboardMeta' => $leaderboardMeta,
        'studyGroups' => $studyGroups,
        'events' => $events,
        'doopNewsPosts' => $doopNewsPosts,
        'newContentCounts' => [
            'quest' => $newQuestCount,
            'guide' => $newGuideCount,
            'event' => $newEventCount,
            'study_group' => $newStudyGroupCount,
            'doop_news' => $newDoopNewsCount,
        ],
        'laravelVersion' => \Illuminate\Foundation\Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
}

private function seenQuestIdSet(int $userId, array $questIds): array
{
    return UserContentRead::seenContentIds($userId, UserContentRead::TYPE_QUEST, $questIds)
        ->mapWithKeys(fn ($id) => [(int) $id => true])
        ->all();
}

private function seenGuideIdSet(int $userId, array $guideIds): array
{
    return UserContentRead::seenContentIds($userId, UserContentRead::TYPE_GUIDE, $guideIds)
        ->mapWithKeys(fn ($id) => [(int) $id => true])
        ->all();
}

private function seenEventIdSet(int $userId, array $eventIds): array
{
    return UserContentRead::seenContentIds($userId, UserContentRead::TYPE_EVENT, $eventIds)
        ->mapWithKeys(fn ($id) => [(int) $id => true])
        ->all();
}

private function seenStudyGroupIdSet(int $userId, array $studyGroupIds): array
{
    return UserContentRead::seenContentIds($userId, UserContentRead::TYPE_STUDY_GROUP, $studyGroupIds)
        ->mapWithKeys(fn ($id) => [(int) $id => true])
        ->all();
}

private function seenDoopNewsIdSet(int $userId, array $postIds): array
{
    return UserContentRead::seenContentIds($userId, UserContentRead::TYPE_DOOP_NEWS, $postIds)
        ->mapWithKeys(fn ($id) => [(int) $id => true])
        ->all();
}

private function isQuestNewForUser(array $quest, array $seenQuestIdSet): bool
{
    $questId = (int) ($quest['id'] ?? 0);
    if ($questId <= 0 || isset($seenQuestIdSet[$questId])) {
        return false;
    }

    if (($quest['user_has_submitted'] ?? false)
        || trim((string) ($quest['user_submission_status'] ?? '')) !== '') {
        return false;
    }

    $createdAt = $this->questTimestamp($quest, 'created_at');

    return $createdAt > 0 && $createdAt >= now()->subDays(30)->getTimestamp();
}

private function isGuideNewForUser(array $guide, array $seenGuideIdSet): bool
{
    $guideId = (int) ($guide['id'] ?? 0);
    if ($guideId <= 0 || isset($seenGuideIdSet[$guideId])) {
        return false;
    }

    $createdAt = $this->questTimestamp($guide, 'created_at');

    return $createdAt > 0 && $createdAt >= now()->subDays(30)->getTimestamp();
}

private function isEventNewForUser(array $event, array $seenEventIdSet): bool
{
    $eventId = (int) ($event['id'] ?? 0);
    if ($eventId <= 0 || isset($seenEventIdSet[$eventId])) {
        return false;
    }

    $createdAt = $this->questTimestamp($event, 'created_at');

    return $createdAt > 0 && $createdAt >= now()->subDays(30)->getTimestamp();
}

private function isStudyGroupNewForUser(array $studyGroup, array $seenStudyGroupIdSet): bool
{
    $studyGroupId = (int) ($studyGroup['id'] ?? 0);
    if ($studyGroupId <= 0 || isset($seenStudyGroupIdSet[$studyGroupId])) {
        return false;
    }

    $createdAt = $this->questTimestamp($studyGroup, 'created_at');

    return $createdAt > 0 && $createdAt >= now()->subDays(30)->getTimestamp();
}

private function isDoopNewsNewForUser(array $post, array $seenDoopNewsIdSet): bool
{
    $postId = (int) ($post['id'] ?? 0);
    if ($postId <= 0 || isset($seenDoopNewsIdSet[$postId])) {
        return false;
    }

    $publishedAt = $this->questTimestamp($post, 'published_at');

    return $publishedAt > 0 && $publishedAt >= now()->subDays(30)->getTimestamp();
}

private function countNewVisibleQuests(int $userId, array $userGroupIds): int
{
    if ($userId <= 0) {
        return 0;
    }

    return Quest::query()
        ->where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                ->orWhereIn('study_group_id', $userGroupIds);
        })
        ->listedForUsers()
        ->where('created_at', '>=', now()->subDays(30))
        ->whereNotExists(function ($query) use ($userId) {
            $query->selectRaw('1')
                ->from('submissions')
                ->whereColumn('submissions.quest_id', 'quests.id')
                ->where('submissions.user_id', $userId);
        })
        ->whereNotExists(function ($query) use ($userId) {
            $query->selectRaw('1')
                ->from('user_content_reads')
                ->whereColumn('user_content_reads.content_id', 'quests.id')
                ->where('user_content_reads.user_id', $userId)
                ->where('user_content_reads.content_type', UserContentRead::TYPE_QUEST)
                ->whereNotNull('user_content_reads.seen_at');
        })
        ->count();
}

private function countNewVisibleGuides(int $userId, array $userGroupIds): int
{
    if ($userId <= 0) {
        return 0;
    }

    return Guide::query()
        ->where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                ->orWhereIn('study_group_id', $userGroupIds);
        })
        ->where('created_at', '>=', now()->subDays(30))
        ->whereNotExists(function ($query) use ($userId) {
            $query->selectRaw('1')
                ->from('user_content_reads')
                ->whereColumn('user_content_reads.content_id', 'guides.id')
                ->where('user_content_reads.user_id', $userId)
                ->where('user_content_reads.content_type', UserContentRead::TYPE_GUIDE)
                ->whereNotNull('user_content_reads.seen_at');
        })
        ->count();
}

private function countNewVisibleEvents(int $userId, array $userGroupIds, ?int $userJobId): int
{
    if ($userId <= 0) {
        return 0;
    }

    return Event::query()
        ->where(function ($query) use ($userGroupIds, $userJobId) {
            $query->where(function ($publicQuery) use ($userJobId) {
                $publicQuery->whereNull('study_group_id')
                    ->where(function ($audienceQuery) use ($userJobId) {
                        $audienceQuery->whereNull('job_id');

                        if (! is_null($userJobId)) {
                            $audienceQuery->orWhere('job_id', $userJobId);
                        }
                    });
            })
                ->orWhereIn('study_group_id', $userGroupIds);
        })
        ->where('created_at', '>=', now()->subDays(30))
        ->whereNotExists(function ($query) use ($userId) {
            $query->selectRaw('1')
                ->from('user_content_reads')
                ->whereColumn('user_content_reads.content_id', 'events.id')
                ->where('user_content_reads.user_id', $userId)
                ->where('user_content_reads.content_type', UserContentRead::TYPE_EVENT)
                ->whereNotNull('user_content_reads.seen_at');
        })
        ->count();
}

private function countNewVisibleStudyGroups(int $userId, ?int $userJobId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $query = \App\Models\StudyGroup::query();

    if (is_null($userJobId)) {
        $query->whereNull('job_id');
    } else {
        $query->where('job_id', $userJobId);
    }

    return $query
        ->where('created_at', '>=', now()->subDays(30))
        ->whereNotExists(function ($query) use ($userId) {
            $query->selectRaw('1')
                ->from('user_content_reads')
                ->whereColumn('user_content_reads.content_id', 'study_groups.id')
                ->where('user_content_reads.user_id', $userId)
                ->where('user_content_reads.content_type', UserContentRead::TYPE_STUDY_GROUP)
                ->whereNotNull('user_content_reads.seen_at');
        })
        ->count();
}

private function countNewVisibleDoopNews(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    return DoopNewsPost::query()
        ->published()
        ->where('published_at', '>=', now()->subDays(30))
        ->whereNotExists(function ($query) use ($userId) {
            $query->selectRaw('1')
                ->from('user_content_reads')
                ->whereColumn('user_content_reads.content_id', 'doop_news_posts.id')
                ->where('user_content_reads.user_id', $userId)
                ->where('user_content_reads.content_type', UserContentRead::TYPE_DOOP_NEWS)
                ->whereNotNull('user_content_reads.seen_at');
        })
        ->count();
}

private function isLeaderboardOnlyPartialRequest(Request $request): bool
{
    $partialKeys = $this->partialDataKeys($request);
    if ($partialKeys->isEmpty()) {
        return false;
    }

    return $partialKeys->every(fn ($key) => in_array($key, ['leaderboards', 'leaderboardMeta'], true));
}

private function partialDataKeys(Request $request): \Illuminate\Support\Collection
{
    $isInertiaRequest = (string) $request->header('X-Inertia', '') !== '';
    if (! $isInertiaRequest) {
        return collect();
    }

    $partialComponent = strtolower(trim((string) $request->header('X-Inertia-Partial-Component', '')));
    if ($partialComponent !== 'home') {
        return collect();
    }

    $partialDataRaw = trim((string) $request->header('X-Inertia-Partial-Data', ''));
    if ($partialDataRaw === '') {
        return collect();
    }

    return collect(explode(',', $partialDataRaw))
        ->map(fn ($key) => trim((string) $key))
        ->filter()
        ->values();
}

private function resolveLeaderboardData(
    string $homeCacheVersion,
    string $jobKey,
    string $classGroupKey,
    ?int $userJobId,
    int $activeClassGroupId
): array {
    $formatLeaderboardPlayers = static function ($players) {
        return $players
            ->map(function ($player) {
                $payload = $player->toArray();
                $payload['exp'] = (int) ($payload['exp'] ?? 0);
                $progress = LevelingService::progress($payload['exp']);
                $payload['level'] = $progress['level'];
                $payload['level_title'] = $progress['title'];
                $payload['level_progress_percent'] = $progress['progress_percent'];
                $payload['exp_in_level'] = $progress['exp_in_level'];
                $payload['exp_to_next_level'] = $progress['exp_needed'];
                $payload['role'] = (string) ($payload['role'] ?? 'Adventurer');
                return $payload;
            })
            ->values();
    };

    $leaderboardBaseQuery = static function () {
        return User::query()
            ->select('id', 'name', 'username', 'profile_photo', 'level', 'exp', 'role')
            ->whereNotIn('role', User::staffRoles())
            ->orderByDesc('exp')
            ->orderByDesc('level')
            ->orderBy('name');
    };

    $globalPlayers = Cache::remember(
        "home.players.v{$homeCacheVersion}.global.job.{$jobKey}",
        now()->addMinutes(5),
        function () use ($leaderboardBaseQuery, $formatLeaderboardPlayers, $userJobId) {
            $query = $leaderboardBaseQuery();

            if (is_null($userJobId)) {
                $query->whereNull('job_id');
            } else {
                $query->where('job_id', $userJobId);
            }

            return $formatLeaderboardPlayers(
                $query->take(10)->get()
            );
        }
    );

    $classPlayers = Cache::remember(
        "home.players.v{$homeCacheVersion}.class.group.{$classGroupKey}",
        now()->addMinutes(5),
        function () use ($activeClassGroupId) {
            if ($activeClassGroupId <= 0) {
                return collect();
            }

            $classMemberIds = DB::table('group_user')
                ->where('study_group_id', $activeClassGroupId)
                ->whereNull('deleted_at')
                ->select('user_id')
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (empty($classMemberIds)) {
                return collect();
            }

            $classQuestIds = Quest::query()
                ->where('study_group_id', $activeClassGroupId)
                ->publishedForAverage()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $classTotalQuests = count($classQuestIds);
            $gradeSumByUser = [];
            $completedCountByUser = [];

            if ($classTotalQuests > 0) {
                $latestSubmissions = Submission::query()
                    ->joinSub(
                        Submission::query()
                            ->whereIn('user_id', $classMemberIds)
                            ->whereIn('quest_id', $classQuestIds)
                            ->selectRaw('MAX(id) as id')
                            ->groupBy('user_id', 'quest_id'),
                        'latest',
                        fn ($join) => $join->on('submissions.id', '=', 'latest.id')
                    )
                    ->get(['submissions.user_id', 'submissions.grade', 'submissions.status']);

                foreach ($latestSubmissions as $submission) {
                    $userIdKey = (int) $submission->user_id;
                    $grade = (int) ($submission->grade ?? 0);
                    $status = (string) ($submission->status ?? '');

                    $gradeSumByUser[$userIdKey] = (int) ($gradeSumByUser[$userIdKey] ?? 0) + $grade;

                    if (in_array($status, ['Approved', 'Rejected'], true)) {
                        $completedCountByUser[$userIdKey] = (int) ($completedCountByUser[$userIdKey] ?? 0) + 1;
                    }
                }
            }

            return User::query()
                ->select('id', 'name', 'username', 'profile_photo', 'level', 'exp', 'role')
                ->whereIn('id', $classMemberIds)
                ->whereNotIn('role', User::staffRoles())
                ->get()
                ->map(function ($player) use ($classTotalQuests, $gradeSumByUser, $completedCountByUser) {
                    $payload = $player->toArray();
                    $payload['exp'] = (int) ($payload['exp'] ?? 0);
                    $progress = LevelingService::progress($payload['exp']);
                    $payload['level'] = $progress['level'];
                    $payload['level_title'] = $progress['title'];
                    $payload['level_progress_percent'] = $progress['progress_percent'];
                    $payload['exp_in_level'] = $progress['exp_in_level'];
                    $payload['exp_to_next_level'] = $progress['exp_needed'];
                    $payload['role'] = (string) ($payload['role'] ?? 'Adventurer');

                    $playerId = (int) ($payload['id'] ?? 0);
                    $gradeSum = (int) ($gradeSumByUser[$playerId] ?? 0);

                    $payload['class_average_grade'] = $classTotalQuests > 0
                        ? round($gradeSum / $classTotalQuests, 1)
                        : 0.0;
                    $payload['class_completed_quests'] = (int) ($completedCountByUser[$playerId] ?? 0);
                    $payload['class_total_quests'] = $classTotalQuests;

                    return $payload;
                })
                ->sort(function (array $a, array $b): int {
                    $cmp = ((float) ($b['class_average_grade'] ?? 0)) <=> ((float) ($a['class_average_grade'] ?? 0));
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = ((int) ($b['class_completed_quests'] ?? 0)) <=> ((int) ($a['class_completed_quests'] ?? 0));
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = ((int) ($b['exp'] ?? 0)) <=> ((int) ($a['exp'] ?? 0));
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    $cmp = ((int) ($b['level'] ?? 1)) <=> ((int) ($a['level'] ?? 1));
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                })
                ->take(10)
                ->values();
        }
    );

    return [
        'globalPlayers' => $globalPlayers,
        'classPlayers' => $classPlayers,
    ];
}

private function questFeedSortTuple(array $quest): array
{
    $status = strtolower(trim((string) ($quest['user_submission_status'] ?? '')));
    $priority = match ($status) {
        'approved' => 4,
        'pending' => 3,
        'rejected' => 2,
        default => $this->isLateQuestForFeed($quest)
            ? 2
            : (($quest['user_has_submitted'] ?? false)
                ? 3
                : ($this->hasQuestTimebox($quest) ? 0 : 1)),
    };

    return [
        $priority,
        -$this->questTimestamp($quest, 'deadline'),
        -$this->questTimestamp($quest, 'available_until'),
        -$this->questTimestamp($quest, 'created_at'),
        -((int) ($quest['id'] ?? 0)),
    ];
}

private function isLateQuestForFeed(array $quest): bool
{
    if (($quest['user_has_submitted'] ?? false) || ($quest['user_has_unlock'] ?? false)) {
        return false;
    }

    if (trim((string) ($quest['user_submission_status'] ?? '')) !== '') {
        return false;
    }

    $deadline = $this->questTimestamp($quest, 'deadline');
    if ($deadline > 0 && $deadline <= now()->getTimestamp()) {
        return true;
    }

    return in_array(strtolower(trim((string) ($quest['status'] ?? ''))), ['done', 'completed'], true);
}

private function hasQuestTimebox(array $quest): bool
{
    return (string) ($quest['schedule_type'] ?? '') === Quest::SCHEDULE_ONCE
        || $this->questTimestamp($quest, 'deadline') > 0;
}

private function questTimestamp(array $quest, string $key): int
{
    $value = $quest[$key] ?? null;

    if (! $value) {
        return 0;
    }

    $timestamp = strtotime((string) $value);
    return $timestamp === false ? 0 : $timestamp;
}

public function landing()
{
    return $this->renderLanding();
}

private function renderLanding()
{
    $landingCacheVersion = CacheVersion::get('landing');
    $hallCacheVersion = CacheVersion::get('hall_of_creations');
    $availableJobs = Cache::remember(
        "landing.jobs.v{$landingCacheVersion}.card_v6",
        now()->addMinutes(10),
        fn () => JobRole::query()
            ->select('id', 'name', 'slug', 'description', 'emblem_path', 'status')
            ->publicVisible()
            ->withCount([
                'users as mentors_count' => fn ($query) => $query->where('role', User::ROLE_MENTOR),
            ])
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [JobRole::STATUS_ACTIVE])
            ->orderBy('name')
            ->get()
            ->map(fn ($job) => [
                'id' => (int) $job->id,
                'name' => (string) $job->name,
                'slug' => (string) $job->slug,
                'description' => $job->description ? (string) $job->description : null,
                'emblem_path' => $job->emblem_path,
                'status' => (string) ($job->status ?? JobRole::STATUS_ACTIVE),
                'mentors_count' => (int) ($job->mentors_count ?? 0),
            ])
            ->values()
            ->all()
    );

    $mentors = Cache::remember(
        "landing.mentors.v{$landingCacheVersion}",
        now()->addMinutes(10),
        fn () => User::query()
            ->select('id', 'name', 'username', 'role', 'profile_photo', 'job_id')
            ->where('role', User::ROLE_MENTOR)
            ->with([
                'job:id,name',
                'detailUser:id,user_id,bio,experience,location,skills',
            ])
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($user) {
                $detail = $user->detailUser;
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'profile_photo' => $user->profile_photo,
                    'job_name' => $user->job?->name,
                    'bio' => $detail?->bio,
                    'experience' => $detail?->experience,
                    'location' => $detail?->location,
                    'skills' => $detail?->skills,
                ];
            })
    );

    $featuredCreations = Cache::remember(
        "landing.featured_creations.v{$hallCacheVersion}",
        now()->addMinutes(5),
        fn () => Creation::query()
            ->publicVisible()
            ->with([
                'user:id,name,username,profile_photo',
                'photos:id,creation_id,path,sort_order',
            ])
            ->withCount(['appreciations', 'insights', 'photos'])
            ->orderByDesc('appreciations_count')
            ->orderByDesc('insights_count')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Creation $creation) {
                $thumbnailUrl = (string) ($creation->photos->first()?->url ?? '');

                return [
                    'id' => (int) $creation->id,
                    'title' => (string) $creation->title,
                    'status' => (string) $creation->status,
                    'progress' => (int) ($creation->progress ?? 0),
                    'category' => $creation->category ? (string) $creation->category : null,
                    'thumbnail_url' => $thumbnailUrl,
                    'appreciations_count' => (int) ($creation->appreciations_count ?? 0),
                    'insights_count' => (int) ($creation->insights_count ?? 0),
                    'photos_count' => (int) ($creation->photos_count ?? $creation->photos->count()),
                    'creator' => [
                        'id' => (int) ($creation->user?->id ?? 0),
                        'name' => (string) ($creation->user?->name ?? ''),
                        'username' => (string) ($creation->user?->username ?? ''),
                    ],
                ];
            })
            ->values()
            ->all()
    );

    $isGuest = !Auth::check();

    return Inertia::render('Landing', [
        'canLogin' => $isGuest && Route::has('login'),
        'canRegister' => $isGuest && Route::has('register'),
        'availableJobs' => $availableJobs,
        'mentors' => $mentors,
        'featuredCreations' => $featuredCreations,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
}
}
