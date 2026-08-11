<?php

use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('scheduled quest stays hidden from students until its release window starts', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Future Event Quest',
        'description' => 'Visible later',
        'difficulty' => 'B-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => Quest::STATUS_IN_PROGRESS,
        'schedule_type' => Quest::SCHEDULE_ONCE,
        'available_from' => now()->addHour(),
        'available_until' => now()->addDay(),
    ]);

    $this->actingAs($student)
        ->get(route('quests.user.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('quests.data', []));

    $this->actingAs($student)
        ->get(route('quests.show', $quest->uuid))
        ->assertRedirect(route('quests.user.index'))
        ->assertSessionHasErrors([
            'quest' => 'QUEST_NOT_YET_AVAILABLE',
        ]);
});

test('future scheduled quest does not affect current average grade before release', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $classroom = StudyGroup::query()->create([
        'name' => 'Schedule Class',
        'description' => 'Quest schedule class',
        'invite_code' => 'SCHEDULE-CLASS',
        'max_members' => 30,
    ]);

    $student->studyGroups()->attach($classroom->id, ['role' => 'member']);

    $availableQuest = Quest::query()->create([
        'title' => 'Available Main Quest',
        'study_group_id' => $classroom->id,
        'status' => Quest::STATUS_AVAILABLE,
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
    ]);

    Quest::query()->create([
        'title' => 'Future Scheduled Quest',
        'study_group_id' => $classroom->id,
        'status' => Quest::STATUS_IN_PROGRESS,
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'schedule_type' => Quest::SCHEDULE_ONCE,
        'available_from' => now()->addDay(),
        'available_until' => now()->addDays(2),
    ]);

    Submission::query()->create([
        'quest_id' => $availableQuest->id,
        'user_id' => $student->id,
        'content' => 'done',
        'status' => 'Approved',
        'grade' => 100,
    ]);

    $response = $this
        ->actingAs($student)
        ->get(route('profile.dashboard'));

    $response->assertOk();
    expect((float) $response->inertiaProps('averageGrade'))->toBe(100.0);
});

test('schedule sync command activates scheduled quest when release time arrives', function () {
    $quest = Quest::query()->create([
        'title' => 'Scheduled Release Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_IN_PROGRESS,
        'schedule_type' => Quest::SCHEDULE_ONCE,
        'available_from' => now()->subMinute(),
        'available_until' => now()->addHour(),
    ]);

    $this->artisan('quests:close-expired')->assertExitCode(0);

    $quest->refresh();

    expect((string) $quest->status)->toBe(Quest::STATUS_AVAILABLE);
});

test('student quest list prioritizes urgent unfinished work before reviewed quests', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $approvedQuest = Quest::query()->create([
        'title' => 'Approved Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'created_at' => now()->addMinutes(5),
    ]);

    $pendingQuest = Quest::query()->create([
        'title' => 'Pending Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'created_at' => now()->addMinutes(4),
    ]);

    $rejectedQuest = Quest::query()->create([
        'title' => 'Rejected Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'created_at' => now()->addMinutes(6),
    ]);

    $lateQuest = Quest::query()->create([
        'title' => 'Late Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_DONE,
        'schedule_type' => Quest::SCHEDULE_ONCE,
        'deadline' => now()->subHour(),
        'available_from' => now()->subDay(),
        'available_until' => now()->addDay(),
        'created_at' => now()->addMinutes(3),
    ]);

    $freshQuest = Quest::query()->create([
        'title' => 'Fresh Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'created_at' => now()->addMinutes(2),
    ]);

    $scheduledQuest = Quest::query()->create([
        'title' => 'Scheduled Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'schedule_type' => Quest::SCHEDULE_ONCE,
        'available_from' => now()->subHour(),
        'available_until' => now()->addHour(),
        'deadline' => now()->addHour(),
        'created_at' => now()->subDay(),
    ]);

    Submission::query()->create([
        'quest_id' => $approvedQuest->id,
        'user_id' => $student->id,
        'content' => 'done',
        'status' => 'Approved',
        'grade' => 100,
    ]);

    Submission::query()->create([
        'quest_id' => $pendingQuest->id,
        'user_id' => $student->id,
        'content' => 'waiting',
        'status' => 'Pending',
        'grade' => 0,
    ]);

    Submission::query()->create([
        'quest_id' => $rejectedQuest->id,
        'user_id' => $student->id,
        'content' => 'needs revision',
        'status' => 'Rejected',
        'grade' => 0,
    ]);

    $response = $this
        ->actingAs($student)
        ->get(route('quests.user.index'));

    $response->assertOk();

    expect(collect($response->inertiaProps('quests.data'))->pluck('title')->all())->toBe([
        $scheduledQuest->title,
        $freshQuest->title,
        $lateQuest->title,
        $rejectedQuest->title,
        $pendingQuest->title,
        $approvedQuest->title,
    ]);
});
