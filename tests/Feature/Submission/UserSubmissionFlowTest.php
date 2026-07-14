<?php

use App\Models\Quest;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

test('multiple choice task bank submission is auto graded', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'MCQ Core Bank',
        'description' => 'Raw intake bank',
        'assessment_type' => 'multiple_choice',
        'is_active' => true,
    ]);

    $questionA = $taskBank->questions()->create([
        'question_text' => '2 + 2 = ?',
        'question_type' => 'multiple_choice',
        'options_json' => ['3', '4', '5'],
        'answer_key' => '4',
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $questionB = $taskBank->questions()->create([
        'question_text' => 'Capital of Indonesia?',
        'question_type' => 'multiple_choice',
        'options_json' => ['Bandung', 'Jakarta', 'Surabaya'],
        'answer_key' => 'Jakarta',
        'weight' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'MCQ Quest',
        'description' => 'Quest with raw intake',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $questionA->uuid => '4',
            $questionB->uuid => 'Jakarta',
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect(str_starts_with((string) $submission->submission_id, 'SUB-'.now()->format('Ymd').'-'))->toBeTrue();
    expect($submission->status)->toBe('Approved');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_EVALUATED);
    expect($submission->preprocess_started)->toBeTrue();
    expect($submission->scores_detail['source'] ?? null)->toBe('task_bank_auto_check');
    expect($submission->scores_detail['answers'][$questionA->uuid] ?? null)->toBe('4');
    expect($submission->scores_detail['answers'][$questionB->uuid] ?? null)->toBe('Jakarta');
    expect((int) $submission->grade)->toBe(100);
    expect((int) $submission->earned_gold)->toBe(1000);
    expect((int) $submission->earned_exp)->toBe(1000);

    $user->refresh();
    expect((int) $user->gold)->toBe(1000);
    expect((int) $user->exp)->toBe(1000);
});

test('word match submission is auto graded from game payload', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Word Match Bank',
        'description' => 'Raw word match intake',
        'assessment_type' => 'word_match',
        'is_active' => true,
    ]);

    $question = $taskBank->questions()->create([
        'question_text' => 'Lengkapi kalimat',
        'question_type' => 'word_match',
        'options_json' => [
            'sentence' => 'Ibu kota Indonesia adalah ___',
            'blanks' => ['Jakarta'],
            'distractors' => ['Bandung', 'Surabaya'],
        ],
        'answer_key' => null,
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Word Match Quest',
        'description' => 'Reward waits for later stages',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $wrongPayload = [
        'placed' => ['Bandung'],
        'correct_count' => 0,
        'total' => 1,
        'timeout' => false,
        'complete' => true,
    ];

    $wrongAttempt = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => json_encode($wrongPayload),
        ],
    ]);
    $wrongAttempt->assertSessionHasNoErrors();

    $wrongSubmission = Submission::query()
        ->where('quest_id', $quest->id)
        ->where('user_id', $user->id)
        ->latest('id')
        ->first();
    expect($wrongSubmission)->not->toBeNull();
    expect($wrongSubmission->status)->toBe('Approved');
    expect($wrongSubmission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_EVALUATED);
    expect($wrongSubmission->scores_detail['source'] ?? null)->toBe('task_bank_auto_check');
    expect($wrongSubmission->scores_detail['answers'][$question->uuid] ?? null)->toBe(json_encode($wrongPayload));
    expect((int) $wrongSubmission->grade)->toBe(0);
    expect((int) $wrongSubmission->earned_gold)->toBe(0);
    expect((int) $wrongSubmission->earned_exp)->toBe(0);

    $perfectPayload = [
        'placed' => ['Jakarta'],
        'correct_count' => 1,
        'total' => 1,
        'timeout' => false,
        'complete' => true,
    ];

    $perfectSubmission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => '[TASK_BANK_RAW_SUBMISSION]',
        'status' => Submission::STATUS_REJECTED,
        'pipeline_status' => Submission::PIPELINE_STATUS_EVALUATED,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $perfectAttempt = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => json_encode($perfectPayload),
        ],
    ]);
    $perfectAttempt->assertSessionHasNoErrors();

    $perfectSubmission->refresh();
    expect($perfectSubmission->status)->toBe('Approved');
    expect($perfectSubmission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_EVALUATED);
    expect($perfectSubmission->scores_detail['answers'][$question->uuid] ?? null)->toBe(json_encode($perfectPayload));
    expect((int) $perfectSubmission->grade)->toBe(100);
    expect((int) $perfectSubmission->earned_gold)->toBe(500);
    expect((int) $perfectSubmission->earned_exp)->toBe(500);
});

test('word match auto grade sums every question payload', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Word Match Multi Bank',
        'description' => 'Multiple word match questions',
        'assessment_type' => 'word_match',
        'is_active' => true,
    ]);

    $first = $taskBank->questions()->create([
        'question_text' => 'Indonesia merdeka pada tanggal ___ Agustus ___.',
        'question_type' => 'word_match',
        'options_json' => [
            'sentence' => 'Indonesia merdeka pada tanggal ___ Agustus ___.',
            'blanks' => ['17', '1945'],
            'distractors' => ['20', '2000'],
        ],
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $second = $taskBank->questions()->create([
        'question_text' => 'Pancasila memiliki ___ sila.',
        'question_type' => 'word_match',
        'options_json' => [
            'sentence' => 'Pancasila memiliki ___ sila.',
            'blanks' => ['lima'],
            'distractors' => ['empat'],
        ],
        'weight' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Word Match Multi Quest',
        'description' => 'Auto grade all blanks',
        'difficulty' => 'C-Rank',
        'reward_gold' => 900,
        'reward_exp' => 900,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $first->uuid => json_encode([
                'placed' => ['17', '2000'],
                'correct_count' => 1,
                'total' => 2,
                'timeout' => false,
                'complete' => true,
            ]),
            $second->uuid => json_encode([
                'placed' => ['lima'],
                'correct_count' => 1,
                'total' => 1,
                'timeout' => false,
                'complete' => true,
            ]),
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Approved');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_EVALUATED);
    expect((int) $submission->grade)->toBe(67);
    expect((int) $submission->earned_gold)->toBe(603);
    expect((int) $submission->earned_exp)->toBe(603);
    expect($submission->scores_detail['correct_questions'] ?? null)->toBe(2);
    expect($submission->scores_detail['total_questions'] ?? null)->toBe(3);
});

test('platforming submission is auto graded from aggregate game payload once', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Platforming Bank',
        'description' => 'Platforming game',
        'assessment_type' => 'platforming',
        'is_active' => true,
    ]);

    $first = $taskBank->questions()->create([
        'question_text' => 'Stage 1',
        'question_type' => 'platforming',
        'options_json' => [
            'stages' => [[
                'prompt' => '2 + 2 = ?',
                'correct_answer' => '4',
                'wrong_answers' => ['3', '5'],
            ]],
        ],
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $second = $taskBank->questions()->create([
        'question_text' => 'Stage 2',
        'question_type' => 'platforming',
        'options_json' => [
            'stages' => [[
                'prompt' => 'Capital of Indonesia?',
                'correct_answer' => 'Jakarta',
                'wrong_answers' => ['Bandung', 'Surabaya'],
            ]],
        ],
        'weight' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Platforming Quest',
        'description' => 'Auto grade aggregate payload',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $payload = json_encode([
        'answers' => [
            ['stage' => 0, 'answer' => '4', 'correct' => true],
            ['stage' => 1, 'answer' => 'Bandung', 'correct' => false],
        ],
        'level' => 1,
        'score' => 1,
        'total' => 2,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $first->uuid => $payload,
            $second->uuid => $payload,
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Approved');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_EVALUATED);
    expect((int) $submission->grade)->toBe(50);
    expect((int) $submission->earned_gold)->toBe(500);
    expect((int) $submission->earned_exp)->toBe(500);
    expect($submission->scores_detail['correct_questions'] ?? null)->toBe(1);
    expect($submission->scores_detail['total_questions'] ?? null)->toBe(2);
});

test('user cannot submit quest from study group they do not belong to', function () {
    $allowedUser = User::factory()->create();
    $blockedUser = User::factory()->create();

    $group = StudyGroup::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Alpha Team',
        'description' => 'Private group',
        'invite_code' => 'INVITE-ALPHA-001',
        'max_members' => 5,
    ]);

    $group->users()->attach($allowedUser->id, ['role' => 'member']);

    $quest = Quest::query()->create([
        'title' => 'Private Group Quest',
        'description' => 'Only for Alpha Team',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'study_group_id' => $group->id,
    ]);

    $response = $this->actingAs($blockedUser)->post(route('submissions.store', $quest->uuid), [
        'content' => 'Attempt from unauthorized user',
    ]);

    $response->assertForbidden();

    $exists = Submission::query()
        ->where('quest_id', $quest->id)
        ->where('user_id', $blockedUser->id)
        ->exists();

    expect($exists)->toBeFalse();
});

test('mixed task bank stores raw answers without requiring full parsing', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Mixed Bank',
        'description' => 'Contains MCQ + essay',
        'assessment_type' => 'mixed',
        'is_active' => true,
    ]);

    $mcq = $taskBank->questions()->create([
        'question_text' => 'Which one is backend framework?',
        'question_type' => 'multiple_choice',
        'options_json' => ['Laravel', 'Figma', 'Blender'],
        'answer_key' => 'Laravel',
        'weight' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $essay = $taskBank->questions()->create([
        'question_text' => 'Explain dependency injection briefly.',
        'question_type' => 'essay',
        'options_json' => null,
        'answer_key' => null,
        'weight' => 3,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Mixed Task Quest',
        'description' => 'Raw answers can be partial',
        'difficulty' => 'B-Rank',
        'reward_gold' => 1200,
        'reward_exp' => 1200,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $partial = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $mcq->uuid => 'Laravel',
        ],
    ]);

    $partial->assertSessionHasNoErrors();

    $partialSubmission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($partialSubmission)->not->toBeNull();
    expect($partialSubmission->status)->toBe('Pending');
    expect($partialSubmission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect($partialSubmission->scores_detail['answered_questions'] ?? null)->toBe(1);
    expect($partialSubmission->scores_detail['answers'][$mcq->uuid] ?? null)->toBe('Laravel');

    $valid = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'Please review my essay answer.',
        'task_answers' => [
            $mcq->uuid => 'Laravel',
            $essay->uuid => 'Dependency injection is providing dependencies from outside.',
        ],
    ]);

    $valid->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect((int) $submission->grade)->toBe(0);
    expect($submission->scores_detail['source'] ?? null)->toBe('raw_task_bank_submission');
    expect($submission->scores_detail['assessment_type'] ?? null)->toBe('mixed');
    expect($submission->scores_detail['answered_questions'] ?? null)->toBe(2);
    expect($submission->scores_detail['answers'][$essay->uuid] ?? null)
        ->toBe('Dependency injection is providing dependencies from outside.');
});

test('essay task bank raw answer is stored without cleanup', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Essay Marker Cleanup Bank',
        'description' => 'Ensure trailing question markers stay raw.',
        'assessment_type' => 'essay',
        'is_active' => true,
    ]);

    $question = $taskBank->questions()->create([
        'question_text' => 'Jelaskan langkah validasi check-in.',
        'question_type' => 'essay',
        'options_json' => null,
        'answer_key' => null,
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Essay Marker Quest',
        'description' => 'Quest to verify raw essay storage.',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => "Validasi check-in dilakukan dengan cek absensi hari ini sebelum simpan.\n\nQ4.",
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()
        ->where('quest_id', $quest->id)
        ->where('user_id', $user->id)
        ->first();

    expect($submission)->not->toBeNull();
    expect((string) ($submission->scores_detail['answers'][$question->uuid] ?? ''))
        ->toBe("Validasi check-in dilakukan dengan cek absensi hari ini sebelum simpan.\n\nQ4.");
});

test('student can update pending raw essay task bank submission before preprocessing', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Pending Essay Update Bank',
        'description' => 'Raw intake bank',
        'assessment_type' => 'essay',
        'is_active' => true,
    ]);

    $question = $taskBank->questions()->create([
        'question_text' => 'Explain 1 + 1 briefly.',
        'question_type' => 'essay',
        'options_json' => null,
        'answer_key' => null,
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Pending Update Quest',
        'description' => 'Pending raw submission can be updated',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $first = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => 'Because one item plus one item makes two items.',
        ],
    ]);
    $first->assertSessionHasNoErrors();

    $second = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'task_answers' => [
            $question->uuid => 'It equals two because the quantities are combined.',
        ],
    ]);

    $second->assertSessionHasNoErrors();

    expect(Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->count())->toBe(1);

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect($submission->scores_detail['answers'][$question->uuid] ?? null)
        ->toBe('It equals two because the quantities are combined.');
});

test('misconfigured multiple_choice task bank is stored as raw without auto mcq scoring', function () {
    $user = User::factory()->create();

    $taskBank = TaskBank::query()->create([
        'name' => 'Broken MCQ Bank',
        'description' => 'Has essay but flagged as multiple_choice',
        'assessment_type' => 'multiple_choice',
        'is_active' => true,
    ]);

    $mcq = $taskBank->questions()->create([
        'question_text' => '1+1=?',
        'question_type' => 'multiple_choice',
        'options_json' => ['1', '2'],
        'answer_key' => '2',
        'weight' => 50,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $essay = $taskBank->questions()->create([
        'question_text' => 'Explain your reasoning.',
        'question_type' => 'essay',
        'options_json' => null,
        'answer_key' => null,
        'weight' => 50,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Broken MCQ Quest',
        'description' => 'Should not block submit',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => 'Available',
        'deadline' => now()->addDay(),
        'task_bank_id' => $taskBank->id,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'notes',
        'task_answers' => [
            $mcq->uuid => '2',
            $essay->uuid => 'Because 1+1 equals 2.',
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect((int) $submission->grade)->toBe(0);
    expect($submission->scores_detail['source'] ?? null)->toBe('raw_task_bank_submission');
    expect($submission->scores_detail['assessment_type'] ?? null)->toBe('multiple_choice');
    expect($submission->scores_detail['answers'][$mcq->uuid] ?? null)->toBe('2');
    expect($submission->scores_detail['answers'][$essay->uuid] ?? null)->toBe('Because 1+1 equals 2.');
    expect($submission->scores_detail['auto_mcq'] ?? null)->toBeNull();
});

test('manual quest pending submission can be re-attempted (reupload file) until deadline', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Manual Quest',
        'description' => 'Allow resubmit pending',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $first = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'first report',
        'file' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
    ]);
    $first->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect($submission->preprocess_started)->toBeFalse();
    expect($submission->file_type)->toBe('pdf');
    expect($submission->file_path)->not->toBeNull();

    $oldPath = (string) $submission->file_path;
    Storage::disk('public')->assertExists($oldPath);

    // Re-attempt: reupload file only (keep content).
    $second = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'file' => UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
    ]);
    $second->assertSessionHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect($submission->preprocess_started)->toBeFalse();
    expect($submission->file_type)->toBe('pdf');
    expect((string) $submission->content)->toBe('first report');
    expect((string) $submission->file_path)->not->toBe('');
    expect((string) $submission->file_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists((string) $submission->file_path);
});

test('manual quest accepts file-only raw submission and marks it pending preprocessing', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'File Only Quest',
        'description' => 'Raw file intake',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'file' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasNoErrors();

    $submission = Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->first();
    expect($submission)->not->toBeNull();
    expect(str_starts_with((string) $submission->submission_id, 'SUB-'.now()->format('Ymd').'-'))->toBeTrue();
    expect((string) $submission->content)->toBe('');
    expect($submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect($submission->preprocess_started)->toBeFalse();
    expect($submission->file_type)->toBe('pdf');
    expect((int) $submission->earned_exp)->toBe(0);
    expect((int) $submission->earned_gold)->toBe(0);
    Storage::disk('public')->assertExists((string) $submission->file_path);
});

test('pending submission cannot be changed after preprocessing has started', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Preprocessing Lock Quest',
        'description' => 'No update after mentor trigger',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'raw before preprocessing',
        'status' => 'Pending',
        'pipeline_status' => Submission::PIPELINE_STATUS_PREPROCESSING,
        'preprocess_started' => true,
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'mutated while preprocessing',
    ]);

    $response->assertSessionHasErrors('submission');

    $submission->refresh();
    expect((string) $submission->content)->toBe('raw before preprocessing');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PREPROCESSING);
    expect($submission->preprocess_started)->toBeTrue();
});

test('manual quest rejects empty raw submission, empty file, and unsupported extension', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Validation Quest',
        'description' => 'Only light validation',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $empty = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => '   ',
    ]);

    $empty->assertSessionHasErrors('content');

    $emptyFile = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
    ]);

    $emptyFile->assertSessionHasErrors('file');

    $unsupported = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
    ]);

    $unsupported->assertSessionHasErrors('file');

    expect(Submission::query()->where('quest_id', $quest->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

test('manual quest pending submission cannot be re-attempted after deadline', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Manual Quest Deadline',
        'description' => 'No update after deadline',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->subMinute(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'initial',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'try update',
    ]);

    $response->assertSessionHasErrors('submission');
    $submission->refresh();
    expect((string) $submission->content)->toBe('initial');
});

test('manual quest submission cannot be re-attempted after it is approved', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $quest = Quest::query()->create([
        'title' => 'Manual Quest Approved',
        'description' => 'No update after approved',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'initial',
        'status' => 'Approved',
        'grade' => 80,
        'earned_exp' => 400,
        'earned_gold' => 400,
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'try update',
    ]);

    $response->assertSessionHasErrors('submission');
    $submission->refresh();
    expect((string) $submission->content)->toBe('initial');
});

test('rejected submission can be retaken before deadline and resets reward to pending review', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'exp' => 400,
        'gold' => 400,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Rejected Retake Quest',
        'description' => 'Can retry after rejection',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => 'Available',
        'deadline' => now()->addDay(),
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $user->id,
        'content' => 'old answer',
        'status' => 'Rejected',
        'grade' => 80,
        'earned_exp' => 400,
        'earned_gold' => 400,
        'file_path' => UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')->store('submissions', 'public'),
    ]);

    $response = $this->actingAs($user)->post(route('submissions.store', $quest->uuid), [
        'content' => 'new answer',
        'file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasNoErrors();

    $submission->refresh();
    $user->refresh();

    expect((string) $submission->status)->toBe('Pending');
    expect($submission->pipeline_status)->toBe(Submission::PIPELINE_STATUS_PENDING_PREPROCESSING);
    expect($submission->preprocess_started)->toBeFalse();
    expect($submission->file_type)->toBe('pdf');
    expect((string) $submission->content)->toBe('new answer');
    expect((int) $submission->earned_exp)->toBe(0);
    expect((int) $submission->earned_gold)->toBe(0);
    expect((int) $user->exp)->toBe(0);
    expect((int) $user->gold)->toBe(0);
});
