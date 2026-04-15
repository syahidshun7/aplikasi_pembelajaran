<?php

use App\Models\Quest;
use App\Models\ShopItem;
use App\Models\ShopTransaction;
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

it('deleting graded submission recalculates user reward totals', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $student = User::factory()->create([
        'exp' => 400,
        'gold' => 400,
    ]);

    $quest = Quest::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Reward Rollback Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Rewarded content',
        'status' => 'Approved',
        'grade' => 80,
        'earned_exp' => 400,
        'earned_gold' => 400,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.submissions.manage.destroy', $submission->uuid))
        ->assertRedirect();

    $student->refresh();

    expect((int) $student->exp)->toBe(0);
    expect((int) $student->gold)->toBe(0);
});

it('deleting submission does not refund gold previously spent for time key purchase', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $student = User::factory()->create([
        'exp' => 400,
        'gold' => 150,
    ]);

    $timeKey = ShopItem::query()->firstWhere('code', 'TIME_KEY');
    expect($timeKey)->not->toBeNull();

    $quest = Quest::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Late Unlock Quest',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
    ]);

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Rewarded late content',
        'status' => 'Approved',
        'grade' => 80,
        'earned_exp' => 400,
        'earned_gold' => 400,
    ]);

    ShopTransaction::query()->create([
        'user_id' => $student->id,
        'shop_item_id' => $timeKey->id,
        'type' => 'purchase',
        'quantity' => 1,
        'gold_change' => -250,
        'note' => 'Purchase from user shop',
        'meta' => [
            'item_code' => 'TIME_KEY',
            'unit_price_gold' => 250,
        ],
    ]);

    ShopTransaction::query()->create([
        'user_id' => $student->id,
        'shop_item_id' => $timeKey->id,
        'type' => 'consume_unlock',
        'quantity' => 1,
        'gold_change' => 0,
        'note' => 'Use Time Key to reopen late quest',
        'meta' => [
            'quest_title' => $quest->title,
        ],
    ]);

    $submission = Submission::query()->where('user_id', $student->id)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.submissions.manage.destroy', $submission->uuid))
        ->assertRedirect();

    $student->refresh();

    expect((int) $student->exp)->toBe(0);
    expect((int) $student->gold)->toBe(-250);
});
