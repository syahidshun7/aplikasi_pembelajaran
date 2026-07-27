<?php

namespace Tests\Feature;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_uses_one_effective_submission_per_quest(): void
    {
        $user = User::factory()->create();
        $quest = Quest::query()->create([
            'title' => 'Effective Profile Quest',
            'difficulty' => 'C-Rank',
            'status' => Quest::STATUS_AVAILABLE,
            'quest_type' => Quest::TYPE_MAIN,
            'grading_attempt' => Quest::GRADE_HIGHEST,
        ]);
        $best = Submission::query()->create([
            'quest_id' => $quest->id,
            'user_id' => $user->id,
            'attempt_number' => 1,
            'status' => Submission::STATUS_APPROVED,
            'grade' => 100,
            'content' => 'best',
        ]);
        Submission::query()->create([
            'quest_id' => $quest->id,
            'user_id' => $user->id,
            'attempt_number' => 2,
            'status' => Submission::STATUS_APPROVED,
            'grade' => 50,
            'content' => 'later but lower',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('averageGrade', 100)
                ->where('totalCompleted', 1)
                ->has('userQuests.data', 1)
                ->where('userQuests.data.0.uuid', (string) $best->uuid)
                ->where('userQuests.data.0.grade', 100)
                ->where('userQuests.data.0.attempt_number', 1)
            );
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/edit');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('testuser', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'username' => (string) $user->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/edit');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile/edit')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile/edit');

        $this->assertNotNull($user->fresh());
    }
}
