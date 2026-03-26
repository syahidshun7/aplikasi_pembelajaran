<?php

namespace App\Observers;

use App\Support\Cache\CacheVersion;

class HallOfCreationsCacheObserver
{
    public function created(): void
    {
        $this->bump();
    }

    public function updated(): void
    {
        $this->bump();
    }

    public function deleted(): void
    {
        $this->bump();
    }

    public function restored(): void
    {
        $this->bump();
    }

    private function bump(): void
    {
        CacheVersion::bump('hall_of_creations');
        CacheVersion::bump('home');
    }
}
