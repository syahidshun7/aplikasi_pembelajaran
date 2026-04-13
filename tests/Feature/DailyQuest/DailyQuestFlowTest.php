<?php

use App\Models\DailyQuest;
use App\Models\DailyQuestDefinition;
use App\Models\Event;
use App\Models\Quest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('login completes daily login quest, claim works, and bonus remains isolated from submission totals', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('lobby', absolute: false));
    $response->assertSessionHas('daily_quest_feedback');

    $dailyQuest = DailyQuest::query()
        ->where('user_id', $user->id)
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_LOGIN)
        ->first();

    expect($dailyQuest)->not->toBeNull();
    expect((string) $dailyQuest->status)->toBe(DailyQuest::STATUS_COMPLETED);
    expect((int) $dailyQuest->progress_value)->toBe(1);
    expect($user->notifications()->count())->toBe(1);
    $notification = $user->notifications()->latest()->first();
    expect((string) data_get($notification?->data, 'category', ''))->toBe('daily_quest');

    $this->actingAs($user)
        ->get(route('lobby'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('dailyQuestBoard.items')
            ->where('dailyQuestBoard.summary.completed', 1)
        );

    $this->actingAs($user)
        ->post(route('daily-quests.claim', $dailyQuest->uuid))
        ->assertSessionHas('daily_quest_feedback')
        ->assertSessionHas('message', 'DAILY_QUEST_REWARD_CLAIMED');

    $dailyQuest->refresh();
    $user->refresh();

    expect((string) $dailyQuest->status)->toBe(DailyQuest::STATUS_CLAIMED);
    expect($dailyQuest->claimed_at)->not->toBeNull();
    expect((int) $user->gold)->toBe(0);
    expect((int) $user->exp)->toBe(0);
});

test('daily quest submission progress only increments on first submission creation', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Daily Submission Quest',
        'description' => 'Manual quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $firstResponse = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'first report',
    ]);

    $firstResponse->assertSessionHasNoErrors();

    $dailyQuest = DailyQuest::query()
        ->where('user_id', $user->id)
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_QUEST_SUBMISSION)
        ->first();

    expect($dailyQuest)->not->toBeNull();
    expect((string) $dailyQuest->status)->toBe(DailyQuest::STATUS_COMPLETED);
    expect((int) $dailyQuest->progress_value)->toBe(1);

    $secondResponse = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'updated report',
    ]);

    $secondResponse->assertSessionHasNoErrors();

    $dailyQuest->refresh();

    expect((int) $dailyQuest->progress_value)->toBe(1);
});

test('self attendance marks event attendance daily quest as completed', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'Daily Event',
        'description' => 'Community sync',
        'sequence_order' => 1,
        'self_attendance_enabled' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $response = $this->actingAs($user)->post(route('events.attendance.self', $event->uuid));

    $response->assertSessionHas('message', 'EVENT_SELF_ATTENDANCE_RECORDED');

    $dailyQuest = DailyQuest::query()
        ->where('user_id', $user->id)
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_EVENT_ATTENDANCE)
        ->first();

    expect($dailyQuest)->not->toBeNull();
    expect((string) $dailyQuest->status)->toBe(DailyQuest::STATUS_COMPLETED);
    expect((int) $dailyQuest->progress_value)->toBe(1);
});

test('expire command marks stale unclaimed daily quests as expired', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $dailyQuest = DailyQuest::query()
        ->where('user_id', $user->id)
        ->where('activity_type', DailyQuestDefinition::ACTIVITY_LOGIN)
        ->firstOrFail();

    $expiredAt = now()->subMinute();

    $dailyQuest->update([
        'status' => DailyQuest::STATUS_COMPLETED,
        'completed_at' => now()->subMinutes(2),
        'claimed_at' => null,
        'expires_at' => $expiredAt,
    ]);

    $this->artisan('daily-quests:expire', [
        '--now' => now()->toDateTimeString(),
    ])->assertExitCode(0);

    $dailyQuest->refresh();

    expect((string) $dailyQuest->status)->toBe(DailyQuest::STATUS_EXPIRED);
});
