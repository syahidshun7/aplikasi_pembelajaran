<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Str;

it('admin can restore and hard delete trashed submission', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $student = User::factory()->create();

    $quest = Quest::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Trash Submission Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Test content',
        'status' => 'Pending',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.submissions.manage.destroy', $submission->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('submissions', ['id' => $submission->id]);

    $this->actingAs($admin)
        ->patch(route('admin.submissions.manage.restore', $submission->uuid))
        ->assertRedirect();

    $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'deleted_at' => null]);

    $this->actingAs($admin)
        ->delete(route('admin.submissions.manage.destroy', $submission->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('submissions', ['id' => $submission->id]);

    $this->actingAs($admin)
        ->delete(route('admin.submissions.manage.force-destroy', $submission->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
});
