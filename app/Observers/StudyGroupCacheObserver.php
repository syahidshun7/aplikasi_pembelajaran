<?php

namespace App\Observers;

use App\Support\Cache\CacheVersion;

class StudyGroupCacheObserver
{
    public function created(): void
    {
        CacheVersion::bump('study_groups');
        CacheVersion::bump('home'); // update events/quests visibility tied to groups
    }

    public function updated(): void
    {
        CacheVersion::bump('study_groups');
        CacheVersion::bump('home');
    }

    public function deleted(): void
    {
        CacheVersion::bump('study_groups');
        CacheVersion::bump('home');
    }

    public function restored(): void
    {
        CacheVersion::bump('study_groups');
        CacheVersion::bump('home');
    }
}
