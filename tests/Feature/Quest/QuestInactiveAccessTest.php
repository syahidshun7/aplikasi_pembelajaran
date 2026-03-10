<?php

namespace Tests\Feature\Quest;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestInactiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_inactive_quest_without_submission(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $quest = Quest::query()->create([
            'title' => 'Inactive Quest',
            'difficulty' => 'C-Rank',
            'reward_gold' => 500,
            'reward_exp' => 500,
            'status' => 'In-Progress',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('quests.show', $quest->uuid));
        $response->assertRedirect(route('quests.user.index'));
        $response->assertSessionHasErrors('quest');
    }

    public function test_user_can_open_inactive_quest_if_already_submitted(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $quest = Quest::query()->create([
            'title' => 'Inactive Quest With Submission',
            'difficulty' => 'C-Rank',
            'reward_gold' => 500,
            'reward_exp' => 500,
            'status' => 'In-Progress',
        ]);

        Submission::query()->create([
            'user_id' => $user->id,
            'quest_id' => $quest->id,
            'content' => 'Submitted before inactive.',
            'status' => 'Pending',
            'grade' => 0,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('quests.show', $quest->uuid));
        $response->assertOk();
    }
}
