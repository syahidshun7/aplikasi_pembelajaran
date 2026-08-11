<?php

use App\Models\Quest;
use App\Models\TaskBank;
use App\Models\User;
use App\Models\UserQuestAttemptSession;
use Inertia\Testing\AssertableInertia;

function createTimedTaskBankQuest(string $assessmentType = 'multiple_choice', int $duration = 60): array
{
    $taskBank = TaskBank::query()->create([
        'name' => "Timed Bank {$assessmentType} ".uniqid(),
        'assessment_type' => $assessmentType,
        'duration' => $duration,
        'has_time_limit' => true,
        'is_active' => true,
    ]);

    $questionType = match ($assessmentType) {
        'platforming' => 'platforming',
        'essay' => 'essay',
        default => 'multiple_choice',
    };
    $question = $taskBank->questions()->create([
        'question_text' => 'Timed question',
        'question_type' => $questionType,
        'options_json' => match ($questionType) {
            'platforming' => ['stages' => [['prompt' => 'Question', 'correct_answer' => 'A', 'wrong_answers' => ['B']]]],
            'multiple_choice' => ['A', 'B'],
            default => null,
        },
        'answer_key' => $questionType === 'multiple_choice' ? 'A' : null,
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => "Timed Quest {$assessmentType} ".uniqid(),
        'description' => 'Quest with server exam timer',
        'difficulty' => 'C-Rank',
        'reward_gold' => 100,
        'reward_exp' => 100,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    return [$quest, $question];
}

it('starts one persistent exam session when a user opens a non game task bank quest', function () {
    $user = User::factory()->create();
    [$quest] = createTimedTaskBankQuest(duration: 60);

    $this->actingAs($user)
        ->get(route('quests.show', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('examTimer.attempt_number', 1)
            ->where('examTimer.duration_minutes', 60)
            ->where('examTimer.expired', false)
        );

    $session = UserQuestAttemptSession::query()->firstOrFail();
    expect((int) $session->started_at->diffInMinutes($session->expires_at))->toBe(60);

    $this->get(route('quests.show', $quest))->assertOk();
    expect(UserQuestAttemptSession::query()->count())->toBe(1);
});

it('rejects a non game task bank submission after server exam time expires', function () {
    $user = User::factory()->create();
    [$quest, $question] = createTimedTaskBankQuest(duration: 60);

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
    $this->travel(61)->minutes();

    $this->post(route('submissions.store', $quest), [
        'task_answers' => [$question->uuid => 'A'],
        'requested_attempt_number' => 1,
    ])
        ->assertSessionHasErrors('submission');

    $this->assertDatabaseMissing('submissions', [
        'quest_id' => $quest->id,
        'user_id' => $user->id,
    ]);
});

it('does not create an exam session for platforming task banks', function () {
    $user = User::factory()->create();
    [$quest] = createTimedTaskBankQuest('platforming', 60);

    $this->actingAs($user)
        ->get(route('quests.show', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('examTimer', null)
        );

    expect(UserQuestAttemptSession::query()->count())->toBe(0);
});

it('shows a simulation timer in staff preview without creating an exam session', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    [$quest] = createTimedTaskBankQuest(duration: 60);

    $this->actingAs($admin)
        ->get(route('quests.user-preview', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('previewMode', true)
            ->where('examTimer.duration_minutes', 60)
            ->where('examTimer.seconds_remaining', 3600)
            ->where('examTimer.simulation', true)
        );

    expect(UserQuestAttemptSession::query()->count())->toBe(0);
});

it('autosaves answers on the server without creating a submission', function () {
    $user = User::factory()->create();
    [$quest, $question] = createTimedTaskBankQuest();

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();

    $this->putJson(route('quests.exam-draft.save', $quest), [
        'attempt_number' => 1,
        'task_answers' => [$question->uuid => 'A'],
        'content' => '',
    ])->assertOk()->assertJsonPath('saved', true);

    $session = UserQuestAttemptSession::query()->firstOrFail();
    expect($session->draft_answers)->toBe([$question->uuid => 'A']);
    $this->assertDatabaseCount('submissions', 0);
});

it('grades unanswered multiple choice questions as zero instead of rejecting a partial submission', function () {
    $user = User::factory()->create();
    [$quest, $question] = createTimedTaskBankQuest();
    $quest->taskBank->questions()->create([
        'question_text' => 'Unanswered question',
        'question_type' => 'multiple_choice',
        'options_json' => ['A', 'B'],
        'answer_key' => 'A',
        'weight' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
    $session = UserQuestAttemptSession::query()->firstOrFail();

    $this->post(route('submissions.store', $quest), [
        'task_answers' => [$question->uuid => 'A'],
        'requested_attempt_number' => 1,
        'client_submission_token' => $session->submission_token,
    ])->assertRedirect();

    $this->assertDatabaseHas('submissions', [
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'grade' => 50,
        'status' => 'Approved',
    ]);
});

it('accepts a confirmed completely unanswered multiple choice submission with zero grade', function () {
    $user = User::factory()->create();
    [$quest] = createTimedTaskBankQuest();

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
    $session = UserQuestAttemptSession::query()->firstOrFail();

    $this->post(route('submissions.store', $quest), [
        'task_answers' => [],
        'confirm_incomplete' => true,
        'requested_attempt_number' => 1,
        'client_submission_token' => $session->submission_token,
    ])->assertRedirect();

    $this->assertDatabaseHas('submissions', [
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'grade' => 0,
        'status' => 'Approved',
    ]);
});

it('finalizes an expired multiple choice draft once when the browser is closed', function () {
    $user = User::factory()->create();
    [$quest, $question] = createTimedTaskBankQuest(duration: 1);

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
    $this->putJson(route('quests.exam-draft.save', $quest), [
        'attempt_number' => 1,
        'task_answers' => [$question->uuid => 'A'],
    ])->assertOk();

    $this->travel(2)->minutes();
    $this->artisan('exams:finalize-expired')->assertSuccessful();
    $this->artisan('exams:finalize-expired')->assertSuccessful();

    $this->assertDatabaseCount('submissions', 1);
    $submission = \App\Models\Submission::query()->firstOrFail();
    expect($submission->grade)->toBe(100)
        ->and($submission->scores_detail['submission_mode'])->toBe('timeout')
        ->and($submission->scores_detail['timed_out_at'])->not->toBeNull();
});

it('creates a pending zero-score essay submission when time expires with no answer', function () {
    $user = User::factory()->create();
    [$quest] = createTimedTaskBankQuest('essay', 1);

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
    $this->travel(2)->minutes();
    $this->artisan('exams:finalize-expired')->assertSuccessful();

    $this->assertDatabaseHas('submissions', [
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'grade' => 0,
        'status' => 'Pending',
    ]);
});

it('does not create an exam session when a non game task bank uses no time mode', function () {
    $user = User::factory()->create();
    [$quest] = createTimedTaskBankQuest(duration: 60);
    $quest->taskBank()->update(['has_time_limit' => false]);

    $this->actingAs($user)
        ->get(route('quests.show', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('examTimer', null)
            ->where('examDraft', null)
        );

    expect(UserQuestAttemptSession::query()->count())->toBe(0);
});

it('offers retake ticket after an expired single attempt has been finalized', function () {
    $user = User::factory()->create();
    [$quest, $question] = createTimedTaskBankQuest(duration: 1);

    $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
    $this->putJson(route('quests.exam-draft.save', $quest), [
        'attempt_number' => 1,
        'task_answers' => [$question->uuid => 'A'],
    ])->assertOk();

    $this->travel(2)->minutes();
    $this->artisan('exams:finalize-expired')->assertSuccessful();

    $this->actingAs($user)
        ->get(route('quests.show', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasSubmitted', true)
            ->where('attemptContext.can_start_new_attempt', false)
            ->where('attemptContext.can_use_retake_ticket', true)
        );
});

it('allows retake tickets for approved or rejected quests after their deadlines', function () {
    $user = User::factory()->create();
    $quests = collect([
        ['quest_type' => 'main', 'submission_status' => 'Approved'],
        ['quest_type' => 'optional', 'submission_status' => 'Approved'],
        ['quest_type' => 'main', 'submission_status' => 'Rejected'],
    ])->map(function (array $scenario) use ($user) {
        [$quest, $question] = createTimedTaskBankQuest();
        $quest->update(['quest_type' => $scenario['quest_type']]);

        $this->actingAs($user)->get(route('quests.show', $quest))->assertOk();
        $session = UserQuestAttemptSession::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->post(route('submissions.store', $quest), [
            'task_answers' => [$question->uuid => 'A'],
            'requested_attempt_number' => 1,
            'client_submission_token' => $session->submission_token,
        ])->assertRedirect();

        \App\Models\Submission::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail()
            ->update(['status' => $scenario['submission_status']]);

        return $quest;
    });

    $ticket = \App\Models\ShopItem::query()->where('code', 'RETAKE_TICKET')->firstOrFail();
    \App\Models\UserInventory::query()->create([
        'user_id' => $user->id,
        'shop_item_id' => $ticket->id,
        'quantity' => 3,
    ]);

    $this->travel(2)->days();

    foreach ($quests as $quest) {
        $this->actingAs($user)
            ->get(route('quests.show', $quest))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quest.quest_type', $quest->quest_type)
                ->where('attemptContext.can_use_retake_ticket', true)
                ->where('attemptContext.retake_ticket_quantity', 3)
            );
    }

    $rejectedQuest = $quests->first(fn (Quest $quest) => $quest->submissions()->latest('id')->first()?->status === 'Rejected');
    $this->post(route('quests.unlock-retake', $rejectedQuest))->assertRedirect();

    $this->assertDatabaseHas('user_quest_attempt_unlocks', [
        'user_id' => $user->id,
        'quest_id' => $rejectedQuest->id,
        'attempt_number' => 2,
        'used_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('quests.show', $rejectedQuest).'?attempt=new')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canSubmit', true)
            ->where('attemptContext.is_new_attempt', true)
            ->where('attemptContext.unlocked_by_ticket', true)
        );
});

it('reopens and submits a late non game quest with a time key after its schedule ends', function () {
    $user = User::factory()->create();
    [$quest, $question] = createTimedTaskBankQuest();
    $quest->taskBank()->update(['has_time_limit' => false]);
    $quest->update([
        'schedule_type' => Quest::SCHEDULE_ONCE,
        'status' => Quest::STATUS_DONE,
        'deadline' => now()->subHour(),
        'available_from' => now()->subDays(2),
        'available_until' => now()->subHour(),
    ]);

    $timeKey = \App\Models\ShopItem::query()->where('code', 'TIME_KEY')->firstOrFail();
    \App\Models\UserInventory::query()->create([
        'user_id' => $user->id,
        'shop_item_id' => $timeKey->id,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('quests.show', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('isLate', true)
            ->where('hasQuestUnlock', false)
            ->where('canSubmit', false)
            ->where('timeKeyQty', 1)
        );

    UserQuestAttemptSession::query()->create([
        'user_id' => $user->id,
        'quest_id' => $quest->id,
        'attempt_number' => 1,
        'submission_token' => (string) \Illuminate\Support\Str::uuid(),
        'started_at' => now()->subHours(2),
        'expires_at' => now()->subHour(),
    ]);

    $this->post(route('quests.unlock-late', $quest))->assertRedirect();
    $this->assertDatabaseMissing('user_quest_attempt_sessions', [
        'user_id' => $user->id,
        'quest_id' => $quest->id,
        'attempt_number' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('quests.show', $quest))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasQuestUnlock', true)
            ->where('canSubmit', true)
            ->where('examTimer', null)
        );

    $this->post(route('submissions.store', $quest), [
        'task_answers' => [$question->uuid => 'A'],
        'requested_attempt_number' => 1,
    ])->assertRedirect();

    $this->assertDatabaseHas('submissions', [
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'grade' => 100,
        'status' => 'Approved',
    ]);
    $this->assertDatabaseHas('user_inventories', [
        'user_id' => $user->id,
        'shop_item_id' => $timeKey->id,
        'quantity' => 0,
    ]);
});
