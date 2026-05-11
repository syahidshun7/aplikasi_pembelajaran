<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('optional quest draft command can commit draft quest', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'ollama');
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Optional Quest: API Error Mapping',
                        'description' => 'Latihan konsistensi response error API.',
                        'difficulty' => 'C-Rank',
                        'learning_objectives' => ['Map exception ke HTTP status'],
                        'success_criteria' => ['Response error seragam'],
                        'reasoning' => 'Submission terbaru masih acak dalam format error.',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $mainQuest = Quest::query()->create([
        'title' => 'Main Error Handling Quest',
        'description' => 'Buat error handler API',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'quest_type' => Quest::TYPE_MAIN,
    ]);

    Submission::query()->create([
        'quest_id' => $mainQuest->id,
        'user_id' => $student->id,
        'content' => 'Implementasi awal error handler API.',
        'status' => 'Approved',
        'grade' => 72,
        'earned_exp' => 360,
        'earned_gold' => 360,
        'feedback' => 'Format response error belum konsisten.',
    ]);

    $this->artisan('ai:optional-quests:generate-drafts', [
        '--max-drafts' => 1,
        '--sample-size' => 60,
    ])->assertExitCode(0);

    $draftQuest = Quest::query()
        ->where('title', 'Optional Quest: API Error Mapping')
        ->first();

    expect($draftQuest)->not->toBeNull();
    expect((string) $draftQuest->quest_type)->toBe(Quest::TYPE_OPTIONAL);
    expect((string) $draftQuest->status)->toBe(Quest::STATUS_IN_PROGRESS);
    expect((string) $draftQuest->schedule_type)->toBe(Quest::SCHEDULE_MANUAL);
});

test('optional quest draft command dry-run does not create quest', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'ollama');
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Optional Quest: Dry Run Probe',
                        'description' => 'Should not persist in dry run mode.',
                        'difficulty' => 'C-Rank',
                        'learning_objectives' => ['Testing dry-run safety'],
                        'success_criteria' => ['No DB write'],
                        'reasoning' => 'Validation of automation safety.',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $student = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $mainQuest = Quest::query()->create([
        'title' => 'Main Dry Run Quest',
        'description' => 'Quest for dry-run command test',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'quest_type' => Quest::TYPE_MAIN,
    ]);

    Submission::query()->create([
        'quest_id' => $mainQuest->id,
        'user_id' => $student->id,
        'content' => 'Submission for dry-run mode.',
        'status' => 'Approved',
        'grade' => 75,
        'earned_exp' => 375,
        'earned_gold' => 375,
    ]);

    $this->artisan('ai:optional-quests:generate-drafts', [
        '--max-drafts' => 1,
        '--sample-size' => 60,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(Quest::query()->where('title', 'Optional Quest: Dry Run Probe')->exists())->toBeFalse();
});

