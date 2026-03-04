<?php

use App\Models\Guide;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Support\Str;

test('user can open guide detail for global guide', function () {
    $user = User::factory()->create();

    $guide = Guide::query()->create([
        'title' => 'Global Guide',
        'description' => 'Panduan global untuk semua user.',
        'study_group_id' => null,
        'file_path' => null,
    ]);

    $response = $this->actingAs($user)->get(route('guides.user.show', $guide->uuid));

    $response->assertOk();
});

test('user cannot open guide detail from another study group', function () {
    $allowedUser = User::factory()->create();
    $blockedUser = User::factory()->create();

    $group = StudyGroup::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Guide Squad',
        'description' => 'Private guide group',
        'invite_code' => 'GUIDE-SQUAD-001',
        'max_members' => 5,
    ]);

    $group->users()->attach($allowedUser->id, ['role' => 'member']);

    $guide = Guide::query()->create([
        'title' => 'Private Guide',
        'description' => 'Hanya untuk anggota group.',
        'study_group_id' => $group->id,
        'file_path' => null,
    ]);

    $response = $this->actingAs($blockedUser)->get(route('guides.user.show', $guide->uuid));

    $response->assertForbidden();
});
