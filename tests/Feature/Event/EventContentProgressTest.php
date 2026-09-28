<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserContentRead;
use Illuminate\Support\Facades\Cache;
use App\Services\EventContentProgressService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

function makeProgressGuide(string $title): Guide
{
    return Guide::query()->create([
        'title' => $title,
        'description' => 'Guide untuk progress event',
    ]);
}

function makeProgressQuest(string $title, string $status = Quest::STATUS_AVAILABLE): Quest
{
    return Quest::query()->create([
        'title' => $title,
        'description' => 'Quest untuk progress event',
        'status' => $status,
        'difficulty' => 'C-Rank',
    ]);
}

test('event created before the progress start date does not expose a progress bar', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'Event Lama',
        'description' => 'Sebelum fitur progress',
        'sequence_order' => 1,
    ]);
    $event->forceFill([
        'created_at' => Carbon::parse('2026-09-24 23:59:59', config('app.timezone')),
        'updated_at' => Carbon::parse('2026-09-24 23:59:59', config('app.timezone')),
    ])->save();

    $guide = makeProgressGuide('Guide Lama');
    $event->guides()->attach($guide->id, ['sort_order' => 1]);

    $this->actingAs($user)
        ->get(route('events.show', $event->uuid))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Events/UserShow')
            ->where('contentProgress.enabled', false)
        );
});

test('opening attached guides and available quests fills the event progress bar', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00', config('app.timezone')));

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'Event September',
        'description' => 'Progress dari guide dan quest',
        'sequence_order' => 1,
    ]);
    $openedGuide = makeProgressGuide('Guide Terbuka');
    $closedGuide = makeProgressGuide('Guide Tertutup');
    $openedQuest = makeProgressQuest('Quest Terbuka');
    $closedQuest = makeProgressQuest('Quest Tertutup');
    $inactiveQuest = makeProgressQuest('Quest Nonaktif', Quest::STATUS_DONE);

    $event->guides()->attach([
        $openedGuide->id => ['sort_order' => 1],
        $closedGuide->id => ['sort_order' => 2],
    ]);
    $event->quests()->attach([
        $openedQuest->id => ['sort_order' => 1],
        $closedQuest->id => ['sort_order' => 2],
        $inactiveQuest->id => ['sort_order' => 3],
    ]);

    UserContentRead::markSeen((int) $user->id, UserContentRead::TYPE_GUIDE, (int) $openedGuide->id);
    UserContentRead::markSeen((int) $user->id, UserContentRead::TYPE_QUEST, (int) $openedQuest->id);

    $this->actingAs($user)
        ->get(route('events.show', $event->uuid))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Events/UserShow')
            ->where('contentProgress.enabled', true)
            ->where('contentProgress.total', 5)
            ->where('contentProgress.opened', 2)
            ->where('contentProgress.percent', 40)
            ->where('contentProgress.completed', false)
            ->where('contentProgress.attendance_done', false)
            ->where('contentProgress.guides_opened', 1)
            ->where('contentProgress.quests_opened', 1)
            ->where('contentProgress.quests_total', 2)
            ->where('event.guides.0.opened_for_user', true)
            ->where('event.guides.1.opened_for_user', false)
        );

    UserContentRead::markSeen((int) $user->id, UserContentRead::TYPE_GUIDE, (int) $closedGuide->id);
    UserContentRead::markSeen((int) $user->id, UserContentRead::TYPE_QUEST, (int) $closedQuest->id);
    EventAttendance::query()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => 'present',
        'checked_at' => now(),
    ]);

    Cache::flush();

    $this->actingAs($user)
        ->get(route('events.user.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Events/UserIndex')
            ->where('events.data.0.content_progress.enabled', true)
            ->where('events.data.0.content_progress.percent', 100)
            ->where('events.data.0.content_progress.completed', true)
        );

    Cache::flush();

    $this->actingAs($user)
        ->get(route('lobby'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('home')
            ->where('events.0.content_progress.enabled', true)
            ->where('events.0.content_progress.percent', 100)
            ->where('events.0.content_progress.completed', true)
            ->where('events.0.content_progress.guides_total', 2)
            ->where('events.0.content_progress.quests_total', 2)
            ->where('events.0.content_progress.attendance_done', true)
        );

    Carbon::setTestNow();
});

test('an event with no required content is already complete', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', config('app.timezone')));

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'Event Kosong',
        'description' => 'Tidak ada materi',
        'sequence_order' => 1,
    ]);

    $summary = app(EventContentProgressService::class)->summariesFor([$event->fresh()], (int) $user->id);

    expect($summary[(int) $event->id])->toMatchArray([
        'enabled' => true,
        'total' => 1,
        'opened' => 0,
        'percent' => 0,
        'completed' => false,
        'attendance_done' => false,
    ]);

    Carbon::setTestNow();
});
