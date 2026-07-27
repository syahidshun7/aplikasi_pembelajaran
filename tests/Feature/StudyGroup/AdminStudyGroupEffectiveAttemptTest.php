<?php

use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('student performance dashboard counts only the effective main quest attempt', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
        'name' => 'Effective Attempt Student',
    ]);
    $group = StudyGroup::query()->create([
        'name' => 'Effective Attempt Group',
        'invite_code' => 'EFFECTIVE-ATTEMPT',
        'max_members' => 10,
        'min_level' => 1,
    ]);
    $group->users()->attach($student->id, ['role' => 'member']);
    $quest = Quest::query()->create([
        'study_group_id' => $group->id,
        'title' => 'Main Effective Quest',
        'difficulty' => 'C-Rank',
        'status' => Quest::STATUS_AVAILABLE,
        'quest_type' => Quest::TYPE_MAIN,
        'grading_attempt' => Quest::GRADE_HIGHEST,
    ]);

    foreach ([[1, 100], [2, 50]] as [$attempt, $grade]) {
        Submission::query()->create([
            'quest_id' => $quest->id,
            'user_id' => $student->id,
            'attempt_number' => $attempt,
            'status' => Submission::STATUS_APPROVED,
            'grade' => $grade,
            'content' => "attempt {$attempt}",
        ]);
    }

    $this->actingAs($admin)
        ->get(route('groups.detail', $group->uuid))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('studentDashboard', 1)
            ->where('studentDashboard.0.main_quest_average', 100)
        );
});
