<?php

use App\Models\Creation;
use App\Models\Quest;
use App\Models\Rubric;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\TaskQuestion;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('penilaian redesign opens submission list and detail routes with real records', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = User::factory()->create(['role' => User::ROLE_USER]);
    $quest = Quest::query()->create(['title' => 'Routing Challenge', 'description' => 'Buat rute', 'status' => Quest::STATUS_AVAILABLE, 'difficulty' => 'C-Rank']);
    $submission = Submission::query()->create(['user_id' => $student->id, 'quest_id' => $quest->id, 'content' => 'Jawaban saya', 'status' => Submission::STATUS_PENDING]);

    $this->actingAs($admin)->get(route('redesign.submissions'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/SubmissionManagement', false)
            ->where('submissions.total', 1)->etc());

    $this->get(route('redesign.submissions.edit', $submission->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/SubmissionEdit', false)
            ->where('submission.content', 'Jawaban saya')->etc());

    $this->get(route('redesign.quests.submissions', $quest->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/QuestSubmissions', false)
            ->where('submissions.total', 1)->etc());

    $this->get(route('redesign.submissions.inspect', $submission->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/SubmissionInspect', false)
            ->where('submission.uuid', $submission->uuid)->etc());
});

test('inspection receives distinct task bank question and answer data for each assessment type', function (string $type, string $questionType) {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = User::factory()->create(['role' => User::ROLE_USER]);
    $bank = TaskBank::query()->create(['name' => "Bank {$type}", 'assessment_type' => $type, 'is_active' => true]);
    $question = TaskQuestion::query()->create(['task_bank_id' => $bank->id, 'question_text' => 'Pertanyaan contoh', 'question_type' => $questionType, 'weight' => 10, 'sort_order' => 1, 'is_active' => true]);
    $quest = Quest::query()->create(['title' => "Quest {$type}", 'description' => 'Tugas', 'status' => Quest::STATUS_AVAILABLE, 'difficulty' => 'C-Rank', 'task_bank_id' => $bank->id]);
    $submission = Submission::query()->create(['user_id' => $student->id, 'quest_id' => $quest->id, 'content' => 'Jawaban', 'status' => Submission::STATUS_PENDING, 'scores_detail' => ['answers' => [$question->uuid => 'Contoh jawaban']]]);

    $this->actingAs($admin)->get(route('redesign.submissions.inspect', $submission->uuid))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/SubmissionInspect', false)
            ->where('submission.quest.task_bank.assessment_type', $type)
            ->where('submission.quest.task_bank.questions.0.question_type', $questionType)
            ->where("submission.scores_detail.answers.{$question->uuid}", 'Contoh jawaban')
            ->etc());
})->with([
    ['multiple_choice', 'multiple_choice'],
    ['essay', 'essay'],
    ['mixed', 'essay'],
    ['platforming', 'platforming'],
    ['word_match', 'word_match'],
]);

test('penilaian redesign opens rubric and creation review routes', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = User::factory()->create(['role' => User::ROLE_USER]);
    $rubric = Rubric::query()->create(['title' => 'Rubrik kode', 'description' => 'Kualitas implementasi', 'mentor_id' => $admin->id]);
    $creation = Creation::query()->create(['user_id' => $student->id, 'title' => 'Portfolio peserta', 'description' => 'Karya peserta']);

    $this->actingAs($admin)->get(route('redesign.rubrics'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/RubricManagement', false)
            ->where('rubrics.total', 1)->etc());
    $this->get(route('redesign.rubrics.create'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/RubricCreate', false)->etc());
    $this->get(route('redesign.rubrics.edit', $rubric->id))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/RubricEditor', false)->etc());
    $this->get(route('redesign.rubrics.show', $rubric->id))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/RubricPreview', false)->etc());
    $this->get(route('redesign.creations.queue', ['review_status' => 'all']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/CreationReviewQueue', false)
            ->where('creations.data.0.slug', $creation->slug)->etc());
    $this->get(route('redesign.creations.review', $creation->slug))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/CreationReview', false)
            ->where('creation.slug', $creation->slug)->etc());
});
