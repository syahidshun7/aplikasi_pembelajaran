<?php

use App\Models\DailyQuest;
use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('optional quest is excluded from academic average calculation', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $classroom = StudyGroup::query()->create([
        'name' => 'Optional Class',
        'description' => 'Bonus assignment class',
        'invite_code' => 'OPTIONAL-CLASS',
        'max_members' => 30,
    ]);

    $student->studyGroups()->attach($classroom->id, ['role' => 'member']);

    $mainQuest = Quest::query()->create([
        'title' => 'Main Quest',
        'study_group_id' => $classroom->id,
        'quest_type' => Quest::TYPE_MAIN,
        'status' => Quest::STATUS_AVAILABLE,
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
    ]);

    $optionalQuest = Quest::query()->create([
        'title' => 'Optional Quest',
        'study_group_id' => $classroom->id,
        'quest_type' => Quest::TYPE_OPTIONAL,
        'status' => Quest::STATUS_AVAILABLE,
        'difficulty' => 'C-Rank',
        'reward_gold' => 900,
        'reward_exp' => 900,
    ]);

    Submission::query()->create([
        'quest_id' => $mainQuest->id,
        'user_id' => $student->id,
        'content' => 'main',
        'status' => 'Approved',
        'grade' => 80,
        'earned_exp' => 500,
        'earned_gold' => 500,
    ]);

    Submission::query()->create([
        'quest_id' => $optionalQuest->id,
        'user_id' => $student->id,
        'content' => 'optional',
        'status' => 'Approved',
        'grade' => 100,
        'earned_exp' => 900,
        'earned_gold' => 900,
    ]);

    $response = $this
        ->actingAs($student)
        ->get(route('profile.dashboard'));

    $response->assertOk();
    expect((float) $response->inertiaProps('averageGrade'))->toBe(80.0);
});

test('reward sync keeps optional quest rewards and claimed daily rewards', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
        'exp' => 0,
        'gold' => 0,
    ]);

    $optionalQuest = Quest::query()->create([
        'title' => 'Optional Reward Quest',
        'quest_type' => Quest::TYPE_OPTIONAL,
        'status' => Quest::STATUS_AVAILABLE,
        'difficulty' => 'C-Rank',
        'reward_gold' => 700,
        'reward_exp' => 700,
    ]);

    Submission::query()->create([
        'quest_id' => $optionalQuest->id,
        'user_id' => $student->id,
        'content' => 'bonus',
        'status' => 'Approved',
        'grade' => 100,
        'earned_exp' => 700,
        'earned_gold' => 700,
    ]);

    DailyQuest::query()->create([
        'daily_quest_definition_id' => 1,
        'user_id' => $student->id,
        'quest_date' => now()->toDateString(),
        'title' => 'Claimed Daily Bonus',
        'description' => 'bonus',
        'activity_type' => 'login',
        'target_value' => 1,
        'progress_value' => 1,
        'reward_exp' => 25,
        'reward_gold' => 10,
        'sort_order' => 1,
        'status' => DailyQuest::STATUS_CLAIMED,
        'completed_at' => now(),
        'claimed_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('users:sync-rewards')->assertExitCode(0);

    $student->refresh();

    expect((int) $student->exp)->toBe(725);
    expect((int) $student->gold)->toBe(710);
});
