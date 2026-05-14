<?php

use App\Models\StudyGroup;
use App\Models\JobRole;
use App\Models\User;
use App\Services\LevelingService;
use Illuminate\Support\Str;

test('user cannot join study group if level is below min_level', function () {
    $job = JobRole::query()->create([
        'name' => 'QA Engineer',
        'slug' => 'qa-engineer',
        'description' => 'Testing path',
    ]);

    $user = User::factory()->create([
        'role' => 'user',
        'email_verified_at' => now(),
        'exp' => 50,
        'job_id' => (int) $job->id,
    ]);

    $group = StudyGroup::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Elite Party',
        'description' => 'High level only',
        'invite_code' => 'GRP-' . strtoupper(Str::random(6)),
        'max_members' => 10,
        'min_level' => 5,
        'job_id' => (int) $job->id,
    ]);

    $userLevel = LevelingService::levelFromExp(50);
    expect($userLevel)->toBeLessThan(5);

    $response = $this->actingAs($user)
        ->post(route('groups.join'), [
            'study_group_uuid' => $group->uuid,
            'reason' => 'I want to join this elite party for learning.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('study_group_uuid');
});

test('user can join study group if level meets min_level', function () {
    $requiredExp = LevelingService::expForLevel(5);

    $job = JobRole::query()->create([
        'name' => 'Backend Engineer',
        'slug' => 'backend-engineer',
        'description' => 'Backend path',
    ]);

    $user = User::factory()->create([
        'role' => 'user',
        'email_verified_at' => now(),
        'exp' => $requiredExp + 100,
        'job_id' => (int) $job->id,
    ]);

    $group = StudyGroup::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Elite Party',
        'description' => 'High level only',
        'invite_code' => 'GRP-' . strtoupper(Str::random(6)),
        'max_members' => 10,
        'min_level' => 5,
        'job_id' => (int) $job->id,
    ]);

    $userLevel = LevelingService::levelFromExp($requiredExp + 100);
    expect($userLevel)->toBeGreaterThanOrEqual(5);

    $response = $this->actingAs($user)
        ->post(route('groups.join'), [
            'study_group_uuid' => $group->uuid,
            'reason' => 'I want to join this elite party for learning.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('message', 'JOIN_REQUEST_SENT_WAITING_APPROVAL');
});
