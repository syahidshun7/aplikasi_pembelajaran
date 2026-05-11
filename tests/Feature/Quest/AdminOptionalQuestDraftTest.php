<?php

use App\Models\Quest;
use App\Models\User;

test('admin can commit optional quest draft as in-progress manual quest', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.quests.optional.commit-draft'), [
            'title' => 'Optional Quest: Latihan Validasi Input',
            'description' => 'Perbaiki validasi endpoint agar lebih tahan edge case.',
            'difficulty' => 'B-Rank',
            'learning_objectives' => ['Memahami validasi request'],
            'success_criteria' => ['Semua edge case utama tertangani'],
            'reasoning' => 'Banyak submission masih gagal validasi data.',
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');

    $quest = Quest::query()->latest('id')->first();
    expect($quest)->not->toBeNull();
    expect((string) $quest->quest_type)->toBe(Quest::TYPE_OPTIONAL);
    expect((string) $quest->status)->toBe(Quest::STATUS_IN_PROGRESS);
    expect((string) $quest->schedule_type)->toBe(Quest::SCHEDULE_MANUAL);
    expect((int) $quest->reward_gold)->toBe(1000);
    expect((int) $quest->reward_exp)->toBe(1000);
});

