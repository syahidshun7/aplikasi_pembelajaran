<?php

use App\Models\Quest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\TaskQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('check-ai uses gemini as primary provider and does not mutate verdict fields', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'ollama');
    config()->set('services.ai.preprocess_with_ollama', false);
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');
    config()->set('services.ai.ollama.base_url', 'http://ollama.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Struktur tugas cukup baik.',
                        'strengths' => ['Flow jelas'],
                        'gaps' => ['Perlu validasi input'],
                        'risk_flags' => ['Edge case belum ditangani'],
                        'suggested_score_range' => '70-85',
                        'suggested_feedback' => 'Tambahkan validasi dan unit test.',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'AI Review Quest',
        'description' => 'Buat endpoint sederhana',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Saya membuat endpoint dan controller sederhana.',
        'status' => 'Pending',
        'grade' => 55,
        'earned_exp' => 120,
        'earned_gold' => 150,
    ]);

    $advisorNote = 'Fokuskan evaluasi pada bukti implementasi route dan controller.';

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAI', ['submission' => $submission->uuid]), [
            'advisor_note' => $advisorNote,
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('provider_used', 'gemini');
    $response->assertJsonPath('is_fallback', false);
    $response->assertJson(fn ($json) => $json->where('status', 'success')
        ->where('provider_used', 'gemini')
        ->where('is_fallback', false)
        ->where('suggested_score_range', fn ($range) => preg_match('/^\d{1,3}-\d{1,3}$/', (string) $range) === 1)
        ->etc());
    $response->assertJsonStructure([
        'confidence' => ['overall', 'rubric', 'task_bank', 'notes'],
    ]);

    $submission->refresh();

    expect((int) $submission->grade)->toBe(55);
    expect((string) $submission->status)->toBe('Pending');
    expect((int) $submission->earned_exp)->toBe(120);
    expect((int) $submission->earned_gold)->toBe(150);
    expect((string) ($submission->scores_detail['ai_advisor']['provider_used'] ?? ''))->toBe('gemini');

    Http::assertSent(function ($request) use ($advisorNote) {
        $payload = $request->data();
        $content = (string) data_get($payload, 'messages.1.content', '');

        return str_contains($content, $advisorNote);
    });
});

test('check-ai falls back to ollama when primary provider fails', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'ollama');
    config()->set('services.ai.preprocess_with_ollama', false);
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');
    config()->set('services.ai.ollama.base_url', 'http://ollama.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response(['error' => 'rate limit'], 429),
        'http://ollama.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Fallback aktif.',
                        'strengths' => ['Sudah ada implementasi'],
                        'gaps' => ['Kurang dokumentasi'],
                        'risk_flags' => [],
                        'suggested_score_range' => '68-80',
                        'suggested_feedback' => 'Tambahkan dokumentasi penggunaan.',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'AI Fallback Quest',
        'description' => 'Fallback test',
        'difficulty' => 'C-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Submission fallback test.',
        'status' => 'Pending',
        'grade' => 60,
        'earned_exp' => 100,
        'earned_gold' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAI', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('provider_used', 'ollama');
    $response->assertJsonPath('is_fallback', true);

    $submission->refresh();
    expect((int) $submission->grade)->toBe(60);
    expect((string) ($submission->scores_detail['ai_advisor']['provider_used'] ?? ''))->toBe('ollama');
});

test('check-ai sends rubric and task bank context with user answers', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'ollama');
    config()->set('services.ai.preprocess_with_ollama', false);
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Context rubric dan task bank diterima.',
                        'strengths' => ['Jawaban sesuai konteks'],
                        'gaps' => [],
                        'risk_flags' => [],
                        'suggested_score_range' => '80-90',
                        'suggested_feedback' => 'Lanjutkan kualitas jawaban.',
                        'rubric_recommendations' => [[
                            'criteria_id' => 1,
                            'criteria_name' => 'Ketepatan Konsep',
                            'suggested_level_id' => 1,
                            'reason' => 'Jawaban tepat dan sesuai indikator rubric.',
                        ]],
                        'task_bank_findings' => [[
                            'question_uuid' => 'q-1',
                            'question_type' => 'multiple_choice',
                            'result' => 'correct',
                            'reason' => 'Pilihan jawaban cocok dengan answer_key.',
                        ]],
                        'question_feedback' => [[
                            'question_uuid' => 'q-1',
                            'question_type' => 'multiple_choice',
                            'question_text' => 'Apa fungsi status code 404?',
                            'user_answer_summary' => 'Memilih opsi B',
                            'score_awarded' => 10,
                            'max_score' => 10,
                            'result' => 'correct',
                            'feedback' => 'Jawaban benar.',
                            'evidence_quotes' => ['B'],
                        ]],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);
    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $rubric = Rubric::query()->create([
        'title' => 'Rubric Praktikum',
        'description' => 'Rubric untuk menilai laporan praktikum.',
        'mentor_id' => $admin->id,
        'max_score' => 100,
    ]);

    $criterion = RubricCriterion::query()->create([
        'rubric_id' => $rubric->id,
        'name' => 'Ketepatan Konsep',
        'weight' => 50,
        'order' => 1,
    ]);

    $level = RubricLevel::query()->create([
        'rubric_id' => $rubric->id,
        'level' => 2,
        'label' => 'Baik',
        'score_value' => 3,
    ]);

    RubricDescription::query()->create([
        'criteria_id' => $criterion->id,
        'level_id' => $level->id,
        'description' => 'Konsep sudah tepat dengan sedikit kekurangan detail.',
    ]);

    $taskBank = TaskBank::query()->create([
        'name' => 'Bank Soal HTTP',
        'description' => 'Kumpulan soal dasar HTTP.',
        'assessment_type' => 'mixed',
        'is_active' => true,
        'rubric_id' => $rubric->id,
    ]);

    $question = TaskQuestion::query()->create([
        'uuid' => 'q-1',
        'task_bank_id' => $taskBank->id,
        'question_text' => 'Apa fungsi status code 404?',
        'question_type' => 'multiple_choice',
        'answer_key' => 'B',
        'weight' => 10,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Laporan Praktikum HTTP',
        'description' => 'Kerjakan soal HTTP dan buat ringkasan.',
        'difficulty' => 'B-Rank',
        'reward_gold' => 1000,
        'reward_exp' => 1000,
        'status' => Quest::STATUS_AVAILABLE,
        'task_bank_id' => $taskBank->id,
        'rubric_id' => $rubric->id,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Jawaban praktikum dikirim.',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
        'scores_detail' => [
            'answers' => [
                $question->uuid => 'B',
            ],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAI', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('rubric_context_present', true);
    $response->assertJsonPath('task_bank_context_present', true);
    $response->assertJsonPath('task_bank_findings.0.question_uuid', 'q-1');
    $response->assertJsonPath('question_feedback.0.question_uuid', 'q-1');
    $response->assertJsonStructure([
        'confidence' => ['overall', 'rubric', 'task_bank', 'notes'],
        'rubric_recommendations',
        'task_bank_findings',
        'question_feedback',
    ]);

    Http::assertSent(function ($request) {
        $data = $request->data();
        $content = (string) data_get($data, 'messages.1.content', '');

        return str_contains($content, 'rubric_context')
            && str_contains($content, 'task_bank_context')
            && str_contains($content, 'task_bank_question_blueprint')
            && str_contains($content, 'question_feedback')
            && str_contains($content, 'Ketepatan Konsep')
            && str_contains($content, 'Apa fungsi status code 404?')
            && str_contains($content, '"user_answer":"B"');
    });
});

test('check-ai preview returns human-readable metadata before scan', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Preview Payload Quest',
        'description' => 'Quest untuk cek preview metadata AI.',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Ini konten submission untuk metadata preview.',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAIPreview', ['submission' => $submission->uuid]), [
            'advisor_note' => 'Mohon prioritaskan akurasi bukti.',
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('preview.quest.title', 'Preview Payload Quest');
    $response->assertJsonPath('preview.advisor_note', 'Mohon prioritaskan akurasi bukti.');
    $response->assertJsonPath('preview.stats.task_bank.question_total', 0);
    $response->assertJsonPath('preview.stats.task_bank.answered_total', 0);
    $response->assertJsonPath('preview.stats.task_bank.unanswered_total', 0);
    $response->assertJsonPath('preview.stats.task_bank.answer_completion_rate', 0);
    $response->assertJsonPath('preview.stats.rubric.present', false);
    $response->assertJsonPath('preview.stats.evidence.chunk_count', 1);
    $response->assertJsonPath('preview.stats.advisor_note_present', true);
    $response->assertJsonStructure([
        'preview' => [
            'quest',
            'artifact',
            'evidence',
            'advisor_note',
            'stats' => [
                'artifact' => ['raw_combined_chars', 'normalized_chars', 'source_flags_count', 'is_truncated'],
                'task_bank' => ['present', 'question_total', 'answered_total', 'unanswered_total', 'answer_completion_rate'],
                'rubric' => ['present', 'criteria_total', 'levels_total', 'matrix_entries_total'],
                'evidence' => ['quality_score', 'chunk_count', 'rubric_evidence_count', 'task_bank_evidence_count'],
                'confidence' => ['overall', 'rubric', 'task_bank'],
                'warnings',
                'advisor_note_present',
            ],
        ],
    ]);
});

test('check-ai preview infers qa totals from report text when task bank is absent', function () {
    config()->set('services.ai.qa_detector.use_ai', false);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Preview QA Detector Quest',
        'description' => 'Quest untuk cek deteksi Q/A dari laporan.',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => implode("\n", [
            '[PDF_EXTRACTED_TEXT]',
            'Soal 1: Apa kegunaan endpoint /health?',
            'Jawaban: Untuk health check service.',
            '2. Jelaskan fungsi status code 404?',
        ]),
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAIPreview', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('preview.stats.task_bank.present', false);
    $response->assertJsonPath('preview.stats.task_bank.question_total', 2);
    $response->assertJsonPath('preview.stats.task_bank.answered_total', 1);
    $response->assertJsonPath('preview.stats.task_bank.unanswered_total', 1);
    $response->assertJsonPath('preview.stats.task_bank.answer_completion_rate', 50);
    $response->assertJsonPath('preview.stats.task_bank.count_source', 'artifact_qa');
});

test('check-ai preview enriches marker-only artifact with task-bank answers for evidence snippets', function () {
    config()->set('services.ai.qa_detector.use_ai', false);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $taskBank = TaskBank::query()->create([
        'name' => 'Task Bank Marker Payload',
        'description' => 'Menguji enrichment jawaban task-bank ke evidence AI.',
        'assessment_type' => 'essay',
        'is_active' => true,
    ]);

    TaskQuestion::query()->create([
        'uuid' => 'q-marker-1',
        'task_bank_id' => $taskBank->id,
        'question_text' => 'Jelaskan fungsi migration pada Laravel.',
        'question_type' => 'essay',
        'answer_key' => '',
        'weight' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Quest Marker Payload',
        'description' => 'Menguji payload lama marker-only.',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'task_bank_id' => $taskBank->id,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => "[TEXT_SUBMISSION]\n[TASK_BANK_SUBMISSION]",
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
        'scores_detail' => [
            'answers' => [
                'q-marker-1' => 'Migration dipakai untuk versioning schema database agar konsisten di semua environment.',
            ],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAIPreview', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('preview.stats.task_bank.present', true);
    $response->assertJsonPath('preview.stats.task_bank.question_total', 1);
    $response->assertJsonPath('preview.stats.task_bank.answered_total', 1);
    $response->assertJsonPath('preview.evidence.task_bank_evidence.0.question_uuid', 'q-marker-1');

    expect((int) data_get($response->json(), 'preview.stats.artifact.normalized_chars', 0))->toBeGreaterThan(80);
    expect((array) data_get($response->json(), 'preview.evidence.task_bank_evidence.0.snippets', []))->not->toBeEmpty();
});

test('check-ai preview uses ai detector for qa totals when enabled', function () {
    config()->set('services.ai.qa_detector.use_ai', true);
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'gemini');
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'question_total' => 4,
                        'answered_total' => 3,
                        'notes' => 'Terdeteksi 4 soal dengan 3 jawaban terisi.',
                        'confidence' => 81,
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $quest = Quest::query()->create([
        'title' => 'Preview QA AI Detector Quest',
        'description' => 'Quest untuk cek AI detector Q/A.',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => implode("\n", [
            '[PDF_EXTRACTED_TEXT]',
            'Soal 1: Apa itu HTTP?',
            'Jawaban: Hypertext Transfer Protocol.',
            '2. Jelaskan fungsi status code 404?',
            '3. Jelaskan fungsi status code 500?',
            '4. Apa itu idempotency?',
            'Jawaban: request berulang tetap efek sama.',
        ]),
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAIPreview', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('preview.stats.task_bank.present', false);
    $response->assertJsonPath('preview.stats.task_bank.question_total', 4);
    $response->assertJsonPath('preview.stats.task_bank.answered_total', 3);
    $response->assertJsonPath('preview.stats.task_bank.unanswered_total', 1);
    $response->assertJsonPath('preview.stats.task_bank.answer_completion_rate', 75);
    $response->assertJsonPath('preview.stats.task_bank.count_source', 'artifact_qa_ai');
    $response->assertJsonPath('preview.stats.task_bank.ai_count_confidence', 81);
    $response->assertJsonPath('preview.stats.task_bank.ai_provider_used', 'gemini');
});

test('check-ai applies stronger score penalty when many task-bank answers are empty', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'gemini');
    config()->set('services.ai.preprocess_with_ollama', false);
    config()->set('services.ai.qa_detector.use_ai', false);
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Struktur laporan cukup baik.',
                        'strengths' => ['Format rapi'],
                        'gaps' => ['Beberapa jawaban kosong'],
                        'risk_flags' => ['Kelengkapan rendah'],
                        'suggested_score_range' => '90-100',
                        'suggested_feedback' => 'Lengkapi seluruh jawaban yang belum terisi.',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $taskBank = TaskBank::query()->create([
        'name' => 'Task Bank Penalti Skor AI',
        'description' => 'Bank soal untuk menguji penalti jawaban kosong.',
        'assessment_type' => 'mixed',
        'is_active' => true,
    ]);

    $questionUuids = ['q-penalty-1', 'q-penalty-2', 'q-penalty-3', 'q-penalty-4'];
    foreach ($questionUuids as $index => $uuid) {
        TaskQuestion::query()->create([
            'uuid' => $uuid,
            'task_bank_id' => $taskBank->id,
            'question_text' => 'Pertanyaan ke-'.($index + 1),
            'question_type' => 'essay',
            'answer_key' => '',
            'weight' => 10,
            'sort_order' => $index + 1,
            'is_active' => true,
        ]);
    }

    $quest = Quest::query()->create([
        'title' => 'Quest Penalti AI',
        'description' => 'Cek penalti saat jawaban task bank tidak lengkap.',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'task_bank_id' => $taskBank->id,
    ]);

    $submission = Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Sebagian jawaban sudah diisi.',
        'status' => 'Pending',
        'grade' => 0,
        'earned_exp' => 0,
        'earned_gold' => 0,
        'scores_detail' => [
            'answers' => [
                'q-penalty-1' => 'Jawaban pertama',
                'q-penalty-2' => 'Jawaban kedua',
            ],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.submissions.checkAI', ['submission' => $submission->uuid]));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('suggested_score_range', '48-58');

    $submission->refresh();
    expect((int) data_get($submission->scores_detail, 'ai_advisor.score_calibration.penalty_points', 0))->toBe(42);
    expect(data_get($submission->scores_detail, 'ai_advisor.score_calibration.reasons', []))->toContain('UNANSWERED_ITEMS_2');
});
