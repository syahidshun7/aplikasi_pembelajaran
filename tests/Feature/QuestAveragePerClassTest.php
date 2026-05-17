<?php

use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedClassQuestStatsForUser(User $user): array
{
    $classA = StudyGroup::query()->create([
        'name' => 'Class A',
        'description' => 'Alpha class',
        'invite_code' => 'CLASSA-CODE',
        'max_members' => 30,
    ]);

    $classB = StudyGroup::query()->create([
        'name' => 'Class B',
        'description' => 'Beta class',
        'invite_code' => 'CLASSB-CODE',
        'max_members' => 30,
    ]);

    $user->studyGroups()->attach($classA->id, ['role' => 'member']);
    $user->studyGroups()->attach($classB->id, ['role' => 'member']);

    $classAQuests = collect(range(1, 10))
        ->map(fn ($idx) => Quest::query()->create([
            'title' => "Class A Quest {$idx}",
            'study_group_id' => $classA->id,
        ]));

    $classBQuest = Quest::query()->create([
        'title' => 'Class B Quest 1',
        'study_group_id' => $classB->id,
    ]);

    foreach ($classAQuests as $quest) {
        Submission::query()->create([
            'quest_id' => $quest->id,
            'user_id' => $user->id,
            'content' => 'Done',
            'status' => 'Approved',
            'grade' => 80,
        ]);
    }

    Submission::query()->create([
        'quest_id' => $classBQuest->id,
        'user_id' => $user->id,
        'content' => 'Done',
        'status' => 'Approved',
        'grade' => 100,
    ]);

    return [
        'class_a_id' => (int) $classA->id,
        'class_b_id' => (int) $classB->id,
    ];
}

test('profile dashboard exposes average grade grouped by class', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    seedClassQuestStatsForUser($student);

    $response = $this
        ->actingAs($student)
        ->get(route('profile.dashboard'));

    $response->assertOk();
    $props = $response->inertiaProps();

    $classAverages = collect($props['classAverages'] ?? []);
    $classA = $classAverages->firstWhere('class_name', 'Class A');
    $classB = $classAverages->firstWhere('class_name', 'Class B');

    expect($classAverages)->toHaveCount(2);
    expect((float) ($props['averageGrade'] ?? 0))->toBe(81.8);
    expect((int) ($props['totalCompleted'] ?? 0))->toBe(11);

    expect($classA)->not->toBeNull();
    expect((float) ($classA['average_grade'] ?? 0))->toBe(80.0);
    expect((int) ($classA['total_quests'] ?? 0))->toBe(10);
    expect((int) ($classA['completed_quests'] ?? 0))->toBe(10);

    expect($classB)->not->toBeNull();
    expect((float) ($classB['average_grade'] ?? 0))->toBe(100.0);
    expect((int) ($classB['total_quests'] ?? 0))->toBe(1);
    expect((int) ($classB['completed_quests'] ?? 0))->toBe(1);
});

test('admin dashboard student list includes average grade grouped by class', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    seedClassQuestStatsForUser($student);

    $response = $this
        ->actingAs($admin)
        ->get(route('dashboard'));

    $response->assertOk();
    $props = $response->inertiaProps();

    $students = collect(data_get($props, 'students.data', []));
    $studentRow = $students->firstWhere('id', $student->id);

    expect($studentRow)->not->toBeNull();
    expect((float) ($studentRow['avg_grade'] ?? 0))->toBe(81.8);
    expect((int) ($studentRow['total_completed'] ?? 0))->toBe(11);

    $classAverages = collect($studentRow['class_averages'] ?? []);
    $classA = $classAverages->firstWhere('class_name', 'Class A');
    $classB = $classAverages->firstWhere('class_name', 'Class B');

    expect($classAverages)->toHaveCount(2);
    expect((float) ($classA['average_grade'] ?? 0))->toBe(80.0);
    expect((int) ($classA['total_quests'] ?? 0))->toBe(10);
    expect((float) ($classB['average_grade'] ?? 0))->toBe(100.0);
    expect((int) ($classB['total_quests'] ?? 0))->toBe(1);
});

