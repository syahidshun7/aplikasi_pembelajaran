<?php

namespace App\Observers;

use App\Support\Cache\CacheVersion;

class HomeFeedObserver
{
    public function created(): void
    {
        CacheVersion::bump('home');
    }

    public function updated(): void
    {
        CacheVersion::bump('home');
    }

    public function deleted(): void
    {
        CacheVersion::bump('home');
    }
}
