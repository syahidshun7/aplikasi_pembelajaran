<?php

use App\Events\JoinGroupRequested;
use App\Models\JobRole;
use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Models\User;
use App\Notifications\JoinGroupRequestNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('join group request dispatches join group requested event', function () {
    Event::fake([JoinGroupRequested::class]);

    $job = JobRole::query()->create([
        'name' => 'Backend',
        'slug' => 'backend',
    ]);

    $requester = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $group = StudyGroup::query()->create([
        'name' => 'Backend Squad',
        'description' => 'Backend learners',
        'invite_code' => 'GRP-BACK01',
        'max_members' => 10,
        'job_id' => $job->id,
    ]);

    $response = $this
        ->actingAs($requester)
        ->post(route('groups.join'), [
            'study_group_uuid' => (string) $group->uuid,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'JOIN_REQUEST_SENT_WAITING_APPROVAL');

    Event::assertDispatched(JoinGroupRequested::class, function (JoinGroupRequested $event) use ($requester, $group) {
        return (int) ($event->joinRequest->user_id ?? 0) === (int) $requester->id
            && (int) ($event->joinRequest->study_group_id ?? 0) === (int) $group->id
            && (string) ($event->joinRequest->status ?? '') === 'pending';
    });
});

test('join group request notifies group admins and global super admin accounts via database notification', function () {
    Notification::fake();

    $job = JobRole::query()->create([
        'name' => 'Frontend',
        'slug' => 'frontend',
    ]);

    $requester = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $groupLeader = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $groupAdmin = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $regularMember = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $globalSuperAdminOutsideGroup = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'job_id' => $job->id,
    ]);

    $globalAdminOutsideGroup = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'job_id' => $job->id,
    ]);

    $group = StudyGroup::query()->create([
        'name' => 'Frontend Guild',
        'description' => 'Frontend learners',
        'invite_code' => 'GRP-FRONT1',
        'max_members' => 10,
        'job_id' => $job->id,
    ]);

    $group->users()->attach($groupLeader->id, ['role' => 'leader']);
    $group->users()->attach($groupAdmin->id, ['role' => 'admin']);
    $group->users()->attach($regularMember->id, ['role' => 'member']);

    $response = $this
        ->actingAs($requester)
        ->post(route('groups.join'), [
            'study_group_uuid' => (string) $group->uuid,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'JOIN_REQUEST_SENT_WAITING_APPROVAL');

    Notification::assertSentTo($groupLeader, JoinGroupRequestNotification::class, function ($notification, $channels) use ($group, $requester, $groupLeader) {
        expect($channels)->toContain('database');
        expect($channels)->not()->toContain('mail');

        $payload = $notification->toArray($groupLeader);

        return (int) data_get($payload, 'meta.requester_id') === (int) $requester->id
            && (string) data_get($payload, 'meta.group_name') === (string) $group->name
            && (string) data_get($payload, 'event') === 'join_requested'
            && (string) data_get($payload, 'action_url') === route('groups.detail', ['uuid' => (string) $group->uuid]);
    });

    Notification::assertSentTo($groupAdmin, JoinGroupRequestNotification::class);
    Notification::assertSentTo($globalSuperAdminOutsideGroup, JoinGroupRequestNotification::class);
    Notification::assertSentTo($globalAdminOutsideGroup, JoinGroupRequestNotification::class);
    Notification::assertNotSentTo($regularMember, JoinGroupRequestNotification::class);
    Notification::assertNotSentTo($requester, JoinGroupRequestNotification::class);
});

test('duplicate pending join request does not redispatch event and does not resend notification', function () {
    Event::fake([JoinGroupRequested::class]);
    Notification::fake();

    $job = JobRole::query()->create([
        'name' => 'Mobile',
        'slug' => 'mobile',
    ]);

    $requester = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $group = StudyGroup::query()->create([
        'name' => 'Mobile Team',
        'description' => 'Mobile learners',
        'invite_code' => 'GRP-MOB001',
        'max_members' => 10,
        'job_id' => $job->id,
    ]);

    StudyGroupJoinRequest::query()->create([
        'study_group_id' => $group->id,
        'user_id' => $requester->id,
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($requester)
        ->post(route('groups.join'), [
            'study_group_uuid' => (string) $group->uuid,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('study_group_uuid');

    Event::assertNotDispatched(JoinGroupRequested::class);
    Notification::assertNothingSent();

    expect(
        StudyGroupJoinRequest::query()
            ->where('study_group_id', $group->id)
            ->where('user_id', $requester->id)
            ->count()
    )->toBe(1);
});
