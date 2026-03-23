<?php

use App\Models\Quest;
use App\Models\User;
use Illuminate\Support\Str;

it('admin can restore and hard delete trashed quest', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $quest = Quest::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Trash Quest',
        'difficulty' => 'A-Rank',
        'reward_gold' => 2500,
        'reward_exp' => 2500,
        'status' => 'Available',
    ]);

    $this->actingAs($admin)
        ->delete(route('quests.destroy', $quest->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('quests', ['id' => $quest->id]);

    $this->actingAs($admin)
        ->patch(route('quests.restore', $quest->uuid))
        ->assertRedirect();

    $this->assertDatabaseHas('quests', ['id' => $quest->id, 'deleted_at' => null]);

    $this->actingAs($admin)
        ->delete(route('quests.destroy', $quest->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('quests', ['id' => $quest->id]);

    $this->actingAs($admin)
        ->delete(route('quests.force-destroy', $quest->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('quests', ['id' => $quest->id]);
});
