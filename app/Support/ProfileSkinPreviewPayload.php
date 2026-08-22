<?php

namespace App\Support;

use App\Models\Creation;
use App\Models\CreationAppreciation;
use App\Models\User;

class ProfileSkinPreviewPayload
{
    public static function make(User $user): array
    {
        $user->loadMissing(['detailUser', 'job']);

        $detail = $user->detailUser;
        $job = $user->job;
        $displayName = (string) ($user->name ?: $user->username ?: '');
        $username = (string) ($user->username ?: ($displayName ? str($displayName)->slug('_') : ''));
        $profilePhoto = (string) ($user->profile_photo ? asset('storage/'.$user->profile_photo) : '');
        $skills = $detail?->skills ?: [];
        $creations = self::publicCreations($user);

        return [
            'type' => 'dooptech:profile-skin-data',
            'user' => [
                'id' => (int) $user->id,
                'name' => $displayName,
                'username' => $username,
                'email' => (string) ($user->email ?: ''),
                'phone' => '',
                'phone_number' => '',
                'location' => (string) ($detail?->location ?: ''),
                'birth_date' => '',
                'birthday' => '',
                'bio' => (string) ($detail?->bio ?: ''),
                'experience' => (string) ($detail?->experience ?: ''),
                'role' => (string) ($user->role ?: ''),
                'job_name' => (string) ($job?->name ?: ''),
                'job_emblem_path' => (string) ($job?->emblem_path ?: ''),
                'skills' => $skills,
                'created_at' => optional($user->created_at)->toISOString(),
                'joined_at' => optional($user->created_at)->toISOString(),
                'lvl' => (int) ($user->lvl ?? 0),
                'level_progress' => [
                    'level' => (int) ($user->lvl ?? 0),
                    'title' => '',
                    'progress' => 0,
                    'progress_percent' => 0,
                ],
            ],
            'activeSkin' => null,
            'stats' => [
                'averageGrade' => null,
                'totalCompleted' => null,
                'creationCount' => count($creations) > 0 ? count($creations) : null,
                'appreciationCount' => ($appreciations = self::totalCreationAppreciations($user)) > 0 ? $appreciations : null,
            ],
            'classAverages' => [],
            'creations' => $creations,
            'urls' => [
                'profilePhoto' => $profilePhoto,
                'hallOfCreations' => route('hall.creations.index'),
                'lobby' => route('lobby'),
            ],
        ];
    }

    private static function publicCreations(User $user): array
    {
        return Creation::query()
            ->publicVisible()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $user->id));
            })
            ->with(['photos:id,creation_id,path,sort_order'])
            ->withCount(['appreciations', 'insights'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Creation $creation) => [
                'id' => (int) $creation->id,
                'slug' => (string) ($creation->slug ?? ''),
                'url' => route('hall.creations.show', ['creation' => $creation->slug ?: $creation->id], false),
                'title' => (string) ($creation->title ?? ''),
                'description' => (string) ($creation->description ?? ''),
                'content' => (string) ($creation->content ?? ''),
                'featured_image' => (string) ($creation->featured_image ?? ''),
                'thumbnail_url' => self::assetUrl((string) ($creation->photos->first()?->url ?? ($creation->featured_image ?? ''))),
                'appreciations_count' => (int) ($creation->appreciations_count ?? 0),
                'insights_count' => (int) ($creation->insights_count ?? 0),
            ])
            ->values()
            ->all();
    }

    private static function totalCreationAppreciations(User $user): int
    {
        return (int) CreationAppreciation::query()
            ->whereHas('creation', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('collaborators', fn ($collaborators) => $collaborators->where('user_id', $user->id));
            })
            ->count();
    }

    private static function assetUrl(string $path): string
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
}
