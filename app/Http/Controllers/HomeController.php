<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use App\Models\Guide; // Ganti dengan nama model materimu jika berbeda
use App\Models\JobRole;
use App\Models\Event;
use App\Models\UserQuestUnlock;
use App\Support\Cache\CacheVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;

class HomeController extends Controller
{
   public function index()
{
    if (!Auth::check()) {
        return $this->renderLanding();
    }

    $userId = Auth::id();
    $user = Auth::user();
    $userJobId = $user?->job_id;

    // 1. Ambil Quest dengan status submission (Logika Kelompok Party)
    $userGroupIds = $userId
        ? $user->studyGroups()
            ->where('study_groups.job_id', $userJobId)
            ->pluck('study_groups.id')
            ->toArray()
        : [];

    $homeCacheVersion = CacheVersion::get('home');
    $jobKey = is_null($userJobId) ? 'none' : (string) (int) $userJobId;
    $groupKey = sha1(json_encode(collect($userGroupIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all()));

    $quests = Cache::remember(
        "home.quests.v{$homeCacheVersion}.job.{$jobKey}.groups.{$groupKey}",
        now()->addMinutes(5),
        fn () => Quest::where(function ($query) use ($userGroupIds) {
            $query->whereNull('study_group_id')
                ->orWhereIn('study_group_id', $userGroupIds);
        })
            ->latest()
            ->take(10)
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

    $quests = $quests->map(function ($quest) use ($submittedQuestIdSet, $submissionStatusesByQuest, $unlockedQuestIdSet) {
        $questId = (int) ($quest['id'] ?? 0);
        $quest['user_has_submitted'] = isset($submittedQuestIdSet[$questId]);
        $quest['user_submission_status'] = $submissionStatusesByQuest[$questId] ?? null;
        $quest['user_has_unlock'] = isset($unlockedQuestIdSet[$questId]);
        return $quest;
    });

    // 2. Ambil Data Materi / Guide (Global + sesuai study group user)
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

    // 3. Ambil Data Leaderboard (Job, Overall, dan Party/Study Group)
    $formatLeaderboardPlayers = static function ($players) {
        return $players
            ->map(function ($player) {
                $payload = $player->toArray();
                $payload['level'] = (int) ($payload['level'] ?? ($payload['lvl'] ?? 1));
                $payload['exp'] = (int) ($payload['exp'] ?? 0);
                $payload['role'] = (string) ($payload['role'] ?? 'Adventurer');
                return $payload;
            })
            ->values();
    };

    $leaderboardBaseQuery = static function () {
        return User::query()
            ->select('id', 'name', 'username', 'profile_photo', 'level', 'exp', 'role')
            ->orderByDesc('exp')
            ->orderByDesc('level')
            ->orderBy('name');
    };

    $players = Cache::remember(
        "home.players.v{$homeCacheVersion}.job.{$jobKey}",
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

    $overallPlayers = Cache::remember(
        "home.players.v{$homeCacheVersion}.overall",
        now()->addMinutes(5),
        fn () => $formatLeaderboardPlayers(
            $leaderboardBaseQuery()->take(10)->get()
        )
    );

    $partyPlayers = Cache::remember(
        "home.players.v{$homeCacheVersion}.party.groups.{$groupKey}",
        now()->addMinutes(5),
        function () use ($leaderboardBaseQuery, $formatLeaderboardPlayers, $userGroupIds) {
            if (empty($userGroupIds)) {
                return collect();
            }

            return $formatLeaderboardPlayers(
                $leaderboardBaseQuery()
                    ->whereHas('studyGroups', fn ($groups) => $groups->whereIn('study_groups.id', $userGroupIds))
                    ->take(10)
                    ->get()
            );
        }
    );

    // 4. Ambil Data Kelompok Belajar (Study Groups)
    $studyGroupCacheVersion = CacheVersion::get('study_groups');
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
                'job_id',
            ])
            
            ->withCount('users')
            ->where('job_id', $userJobId)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($group) => $group->toArray())
    );

    $groupRequestStatuses = $userId
        ? StudyGroupJoinRequest::where('user_id', $userId)->pluck('status', 'study_group_id')->toArray()
        : [];

    $studyGroups = $studyGroups->map(function ($group) use ($userGroupIds, $groupRequestStatuses) {
        $groupId = (int) ($group['id'] ?? 0);
        $group['is_member'] = in_array($groupId, $userGroupIds, true);
        $group['join_request_status'] = $groupRequestStatuses[$groupId] ?? null;
        return $group;
    });

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
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('starts_at')
            ->orderBy('sequence_order')
            ->take(10)
            ->get()
            ->map(fn ($event) => $event->toArray())
    );

    return Inertia::render('home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'quests' => $quests,
        'materi' => $materi,
        'players' => $players,
        'leaderboards' => [
            'job' => $players,
            'overall' => $overallPlayers,
            'party' => $partyPlayers,
        ],
        'studyGroups' => $studyGroups,
        'events' => $events,
        'laravelVersion' => \Illuminate\Foundation\Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
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
        "landing.jobs.v{$landingCacheVersion}.card_v5",
        now()->addMinutes(10),
        fn () => JobRole::query()
            ->select('id', 'name', 'slug', 'description', 'emblem_path')
            ->withCount([
                'users as mentors_count' => fn ($query) => $query->where('role', User::ROLE_MENTOR),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($job) => [
                'id' => (int) $job->id,
                'name' => (string) $job->name,
                'slug' => (string) $job->slug,
                'description' => $job->description ? (string) $job->description : null,
                'emblem_path' => $job->emblem_path,
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
