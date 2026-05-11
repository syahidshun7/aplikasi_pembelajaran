<?php

use App\Models\Quest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('admin can generate optional quest preview using ai provider', function () {
    config()->set('services.ai.primary', 'gemini');
    config()->set('services.ai.fallback', 'ollama');
    config()->set('services.ai.gemini.api_key', 'test-key');
    config()->set('services.ai.gemini.base_url', 'https://gemini.test/v1');

    Http::fake([
        'https://gemini.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Optional Quest: Input Validation Hardening',
                        'description' => 'Perkuat validasi input dan edge-case handling pada endpoint.',
                        'difficulty' => 'B-Rank',
                        'learning_objectives' => ['Validasi request', 'Error handling konsisten'],
                        'success_criteria' => ['Semua input invalid ditolak dengan pesan tepat'],
                        'reasoning' => 'Banyak submission belum konsisten menangani input invalid.',
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
        'title' => 'Main Quest Validator',
        'description' => 'Bangun endpoint validasi data',
        'difficulty' => 'C-Rank',
        'reward_gold' => 500,
        'reward_exp' => 500,
        'status' => Quest::STATUS_AVAILABLE,
        'quest_type' => Quest::TYPE_MAIN,
    ]);

    Submission::query()->create([
        'quest_id' => $quest->id,
        'user_id' => $student->id,
        'content' => 'Saya membuat endpoint validasi dasar.',
        'status' => 'Approved',
        'grade' => 70,
        'earned_exp' => 350,
        'earned_gold' => 350,
        'feedback' => 'Perlu konsistensi error handling.',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.quests.optional.generate-preview'), [
            'sample_size' => 60,
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('provider_used', 'gemini');
    $response->assertJsonPath('draft.difficulty', 'B-Rank');
    $response->assertJsonPath('draft.title', 'Optional Quest: Input Validation Hardening');
});

