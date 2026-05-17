<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('admin can preview pdf submission file inline', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $quest = Quest::query()->create([
        'title' => 'PDF Preview Quest',
        'description' => 'Quest for admin file preview',
        'difficulty' => 'C-Rank',
        'reward_gold' => 300,
        'reward_exp' => 300,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    Storage::disk('public')->put('submissions/admin_preview_test.pdf', '%PDF-1.4 sample');

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'pdf attachment',
        'status' => 'Pending',
        'file_path' => 'submissions/admin_preview_test.pdf',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.submissions.file', ['submission' => $submission->uuid]));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
    expect((string) $response->headers->get('content-disposition'))->toContain('inline');
});

test('admin file preview returns not found when submission file is missing', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $quest = Quest::query()->create([
        'title' => 'Broken File Quest',
        'description' => 'Quest with missing file',
        'difficulty' => 'C-Rank',
        'reward_gold' => 300,
        'reward_exp' => 300,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'broken path',
        'status' => 'Pending',
        'file_path' => 'submissions/not-found.pdf',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.submissions.file', ['submission' => $submission->uuid]))
        ->assertNotFound();
});
