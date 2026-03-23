<?php

use App\Models\StudyGroup;
use App\Models\User;

it('admin can restore and hard delete trashed study group', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $group = StudyGroup::query()->create([
        'name' => 'Ops Group',
        'description' => 'Group for trash flow test',
        'invite_code' => 'GRP-TRASH1',
        'max_members' => 5,
    ]);

    $this->actingAs($admin)
        ->delete(route('groups.destroy', $group->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('study_groups', ['id' => $group->id]);

    $this->actingAs($admin)
        ->patch(route('groups.restore', $group->uuid))
        ->assertRedirect();

    $this->assertDatabaseHas('study_groups', ['id' => $group->id, 'deleted_at' => null]);

    $this->actingAs($admin)
        ->delete(route('groups.destroy', $group->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('study_groups', ['id' => $group->id]);

    $this->actingAs($admin)
        ->delete(route('groups.force-destroy', $group->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('study_groups', ['id' => $group->id]);
});
