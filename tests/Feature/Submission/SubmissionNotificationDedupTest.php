<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\AssignmentSubmittedNotification;
use App\Services\LmsNotificationService;

test('submission received notification is deduplicated for super admin and staff recipients', function () {
    $superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Dedup Notification Quest',
        'description' => 'Quest for testing submission notification deduplication',
        'difficulty' => 'C-Rank',
        'reward_gold' => 100,
        'reward_exp' => 100,
        'status' => Quest::STATUS_AVAILABLE,
        'deadline' => now()->addDay(),
        'task_bank_id' => null,
        'study_group_id' => null,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => (int) $quest->id,
        'user_id' => (int) $student->id,
        'content' => 'Initial submission payload',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $service = app(LmsNotificationService::class);
    $service->notifySubmissionReceived($submission);
    $service->notifySubmissionReceived($submission);

    expect(
        $superAdmin->notifications()->where('type', AssignmentSubmittedNotification::class)->count()
    )->toBe(1);

    expect(
        $admin->notifications()->where('type', AssignmentSubmittedNotification::class)->count()
    )->toBe(1);

    expect(
        $mentor->notifications()->where('type', AssignmentSubmittedNotification::class)->count()
    )->toBe(1);
});

