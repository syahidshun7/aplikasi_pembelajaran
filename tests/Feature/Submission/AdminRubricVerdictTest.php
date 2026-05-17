<?php

use App\Models\Quest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricLevel;
use App\Models\Submission;
use App\Models\User;

test('admin can grade manual quest submission using rubric and verdict is stored under scores_detail.verdict', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $mentor = User::factory()->create([
        'role' => 'mentor',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $rubric = Rubric::query()->create([
        'title' => 'Essay Rubric',
        'description' => 'Test rubric',
        'mentor_id' => $mentor->id,
        'max_score' => 100,
    ]);

    $poor = RubricLevel::query()->create([
        'rubric_id' => $rubric->id,
        'level' => 1,
        'label' => 'Poor',
        'score_value' => 1,
    ]);

    $excellent = RubricLevel::query()->create([
        'rubric_id' => $rubric->id,
        'level' => 2,
        'label' => 'Excellent',
        'score_value' => 4,
    ]);

    $criterionA = RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Content',
        'weight' => 60,
        'order' => 1,
    ]);

    $criterionB = RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Structure',
        'weight' => 40,
        'order' => 2,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Essay Quest',
        'description' => 'Manual quest with rubric',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => null,
        'rubric_id' => $rubric->id,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'answers submitted',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
        'scores_detail' => null,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.submissions.verdict', [
        'submission' => $submission->uuid,
    ]), [
        'status' => 'Approved',
        'feedback' => 'Good work',
        'selected_levels' => [
            $criterionA->id => $excellent->id,
            $criterionB->id => $poor->id,
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission->refresh();
    expect((int) $submission->grade)->toBe(70);
    expect((int) $submission->earned_gold)->toBe(700);
    expect((int) $submission->earned_exp)->toBe(700);
    expect($submission->scores_detail['verdict']['source'] ?? null)->toBe('rubric');
    expect((int) ($submission->scores_detail['verdict']['rubric_id'] ?? 0))->toBe((int) $rubric->id);
});
