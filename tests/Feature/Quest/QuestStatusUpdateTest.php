<?php

namespace Tests\Feature\Quest;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_quest_store_sets_status_from_active_flag(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $deadline = now()->addDays(3)->toDateTimeString();

        $response = $this->post(route('quests.store'), [
            'title' => 'Quest Alpha',
            'difficulty' => 'C-Rank',
            'reward_gold' => 500,
            'reward_exp' => 500,
            'description' => 'Test quest.',
            'is_active' => false,
            'study_group_id' => null,
            'task_bank_id' => null,
            'rubric_id' => null,
            'deadline' => $deadline,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $quest = Quest::query()->latest('id')->firstOrFail();
        $this->assertSame('In-Progress', $quest->status);
    }

    public function test_quest_update_sets_status_from_active_flag(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $deadline = now()->addDays(3)->toDateTimeString();

        $quest = Quest::query()->create([
            'title' => 'Quest Beta',
            'difficulty' => 'C-Rank',
            'reward_gold' => 500,
            'reward_exp' => 500,
            'description' => 'Test quest.',
            'status' => 'Available',
            'deadline' => $deadline,
        ]);

        $response = $this->patch(route('quests.update', $quest->uuid), [
            'title' => $quest->title,
            'difficulty' => $quest->difficulty,
            'reward_gold' => $quest->reward_gold,
            'reward_exp' => $quest->reward_exp,
            'description' => $quest->description,
            'is_active' => true,
            'study_group_id' => null,
            'task_bank_id' => null,
            'rubric_id' => null,
            'deadline' => $deadline,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $quest->refresh();
        $this->assertSame('Available', $quest->status);
    }
}
