<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Observers\HomeFeedObserver;
use App\Observers\StudyGroupCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Cache invalidation for home dashboard listings
        Quest::observe(HomeFeedObserver::class);
        Guide::observe(HomeFeedObserver::class);
        Event::observe(HomeFeedObserver::class);
        StudyGroup::observe(StudyGroupCacheObserver::class);

        // Global IP throttling to reduce automated abuse on auth endpoints.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('login-ip', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('password-reset-request', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
