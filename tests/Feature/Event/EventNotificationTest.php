<?php

use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\Quest;
use App\Models\User;
use App\Models\JobRole;
use App\Notifications\AssignmentSubmittedNotification;
use App\Notifications\EventPublishedNotification;
use Illuminate\Support\Facades\Notification;

test('creating a new event sends in-app and email notifications to relevant learners only', function () {
    Notification::fake();

    $job = JobRole::query()->create([
        'name' => 'Backend',
        'slug' => 'backend',
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $member = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $job->id,
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $outsider = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $studyGroup = StudyGroup::query()->create([
        'name' => 'Backend Squad',
        'description' => 'Study group for backend learners',
        'invite_code' => 'BACKEND42',
        'max_members' => 20,
        'job_id' => $job->id,
    ]);

    $studyGroup->users()->attach($member->id, ['role' => 'member']);
    $studyGroup->users()->attach($student->id, ['role' => 'member']);
    $studyGroup->users()->attach($mentor->id, ['role' => 'mentor']);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), [
            'title' => 'Sprint Review Event',
            'description' => 'Review project and next study plan',
            'sequence_order' => 1,
            'study_group_id' => $studyGroup->id,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'EVENT_CREATED');

    Notification::assertSentTo($member, EventPublishedNotification::class, function ($notification, $channels) {
        expect($channels)->toContain('database');
        expect($channels)->toContain('broadcast');
        expect($channels)->toContain('mail');

        return true;
    });

    Notification::assertSentTo($student, EventPublishedNotification::class);
    Notification::assertNotSentTo($admin, EventPublishedNotification::class);
    Notification::assertNotSentTo($mentor, EventPublishedNotification::class);
    Notification::assertNotSentTo($outsider, EventPublishedNotification::class);
});

test('public event emails are sent only to users in the targeted job audience', function () {
    Notification::fake();

    $frontendJob = JobRole::query()->create([
        'name' => 'Frontend',
        'slug' => 'frontend',
    ]);

    $backendJob = JobRole::query()->create([
        'name' => 'Backend',
        'slug' => 'backend',
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $frontendLearner = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $frontendJob->id,
    ]);

    $frontendStudent = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'job_id' => $frontendJob->id,
    ]);

    $backendLearner = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $backendJob->id,
    ]);

    $frontendMentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $frontendJob->id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.events.store'), [
            'title' => 'Frontend Townhall',
            'description' => 'Open event for frontend learners',
            'sequence_order' => 2,
            'study_group_id' => null,
            'job_id' => $frontendJob->id,
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'ends_at' => now()->addDays(2)->addHour()->toDateTimeString(),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'EVENT_CREATED');

    Notification::assertSentTo($frontendLearner, EventPublishedNotification::class);
    Notification::assertSentTo($frontendStudent, EventPublishedNotification::class);
    Notification::assertNotSentTo($backendLearner, EventPublishedNotification::class);
    Notification::assertNotSentTo($frontendMentor, EventPublishedNotification::class);
    Notification::assertNotSentTo($admin, EventPublishedNotification::class);
});

test('assignment submitted notification never uses mail channel for admin review flow', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Queue Safety Quest',
        'status' => 'Available',
        'difficulty' => 'C-Rank',
        'reward_exp' => 100,
        'reward_gold' => 100,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'My answer',
        'status' => 'Pending',
    ]);

    $notification = new AssignmentSubmittedNotification($submission);

    expect($notification->via($student))->toBe(['database', 'broadcast']);
});
