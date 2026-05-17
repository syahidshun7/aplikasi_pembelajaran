<?php

use App\Models\DoopLabTodo;
use App\Models\User;
use App\Notifications\DoopLabTodoDeadlineReminderNotification;
use Illuminate\Support\Facades\Notification;

test('dooplab deadline reminder sends in-app notification and optional email based on todo setting', function () {
    Notification::fake();

    $emailEnabledOwner = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $inAppOnlyOwner = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $futureStartOwner = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $emailEnabledTodo = DoopLabTodo::query()->create([
        'title' => 'Todo email reminder',
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'owner_user_id' => $emailEnabledOwner->id,
        'notify_deadline_email' => true,
        'start_at' => now()->subHour(),
        'deadline' => now()->addHours(4),
        'is_completed' => false,
    ]);

    $inAppOnlyTodo = DoopLabTodo::query()->create([
        'title' => 'Todo in-app reminder',
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'owner_user_id' => $inAppOnlyOwner->id,
        'notify_deadline_email' => false,
        'start_at' => now()->subHour(),
        'deadline' => now()->addHours(6),
        'is_completed' => false,
    ]);

    $futureStartTodo = DoopLabTodo::query()->create([
        'title' => 'Todo start in future',
        'assignment_mode' => DoopLabTodo::MODE_SELF,
        'owner_user_id' => $futureStartOwner->id,
        'notify_deadline_email' => true,
        'start_at' => now()->addHours(2),
        'deadline' => now()->addHours(8),
        'is_completed' => false,
    ]);

    $this->artisan('notifications:send-dooplab-deadline-reminders')
        ->assertExitCode(0);

    Notification::assertSentTo($emailEnabledOwner, DoopLabTodoDeadlineReminderNotification::class, function ($notification, $channels) {
        expect($channels)->toContain('database');
        expect($channels)->toContain('broadcast');
        expect($channels)->toContain('mail');

        return true;
    });

    Notification::assertSentTo($inAppOnlyOwner, DoopLabTodoDeadlineReminderNotification::class, function ($notification, $channels) {
        expect($channels)->toContain('database');
        expect($channels)->toContain('broadcast');
        expect($channels)->not->toContain('mail');

        return true;
    });

    Notification::assertNotSentTo($futureStartOwner, DoopLabTodoDeadlineReminderNotification::class);

    expect($emailEnabledTodo->fresh()->deadline_reminded_at)->not->toBeNull();
    expect($inAppOnlyTodo->fresh()->deadline_reminded_at)->not->toBeNull();
    expect($futureStartTodo->fresh()->deadline_reminded_at)->toBeNull();
});

