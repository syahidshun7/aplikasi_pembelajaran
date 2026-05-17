<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('admin can download all submission files of a quest as zip archive', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $quest = Quest::query()->create([
        'title' => 'Download Bundle Quest',
        'description' => 'Quest for archive download testing',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    Storage::disk('public')->put('submissions/demo_one.pdf', 'demo-pdf-content');

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'submission with file',
        'status' => 'Pending',
        'file_path' => 'submissions/demo_one.pdf',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.quests.submissions.download-files', ['quest' => $quest->uuid]));

    $response->assertOk();
    expect((string) $response->headers->get('content-disposition'))->toContain('.zip');
});

test('download submission files returns validation error when no files exist', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create();

    $quest = Quest::query()->create([
        'title' => 'No File Quest',
        'description' => 'Quest without file upload',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'submission without file',
        'status' => 'Pending',
        'file_path' => null,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.quests.submissions', ['quest' => $quest->uuid]))
        ->get(route('admin.quests.submissions.download-files', ['quest' => $quest->uuid]));

    $response->assertRedirect(route('admin.quests.submissions', ['quest' => $quest->uuid]));
    $response->assertSessionHasErrors('download');
});
