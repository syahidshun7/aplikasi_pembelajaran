<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class LocalArtifactPreprocessorService
{
    public function __construct(
        private readonly OllamaClient $ollamaClient,
        private readonly AiResponseJsonParser $jsonParser,
    ) {
    }

    /**
     * @return array{normalized_text:string, key_points:array<int, string>, used_local_preprocessor:bool}
     */
    public function preprocess(string $artifactText): array
    {
        $artifactText = trim($artifactText);
        if ($artifactText === '' || $artifactText === '[NO_READABLE_ARTIFACT_FOUND]') {
            return [
                'normalized_text' => $artifactText,
                'key_points' => [],
                'used_local_preprocessor' => false,
            ];
        }

        if (! (bool) config('services.ai.preprocess_with_ollama', false)) {
            return [
                'normalized_text' => $artifactText,
                'key_points' => [],
                'used_local_preprocessor' => false,
            ];
        }

        if (! $this->isOllamaAvailable()) {
            return [
                'normalized_text' => $artifactText,
                'key_points' => [],
                'used_local_preprocessor' => false,
            ];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Kamu adalah preprocessor teks submission. Balas HANYA JSON valid.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n", [
                    'Normalisasi teks submission berikut: buang noise, rapikan kalimat, pertahankan fakta teknis.',
                    'Output JSON schema:',
                    '{"normalized_text":"string","key_points":["string"]}',
                    'Teks sumber:',
                    $artifactText,
                ]),
            ],
        ];

        try {
            $result = $this->ollamaClient->chat($messages);
            $decoded = $this->jsonParser->decode((string) ($result['content'] ?? ''));
            $normalized = trim((string) ($decoded['normalized_text'] ?? ''));

            if ($normalized === '') {
                return [
                    'normalized_text' => $artifactText,
                    'key_points' => [],
                    'used_local_preprocessor' => false,
                ];
            }

            $keyPoints = collect($decoded['key_points'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->take(8)
                ->values()
                ->all();

            return [
                'normalized_text' => $normalized,
                'key_points' => $keyPoints,
                'used_local_preprocessor' => true,
            ];
        } catch (Throwable) {
            return [
                'normalized_text' => $artifactText,
                'key_points' => [],
                'used_local_preprocessor' => false,
            ];
        }
    }

    private function isOllamaAvailable(): bool
    {
        return (bool) Cache::remember('ai.ollama.availability', now()->addSeconds(30), function () {
            $baseUrl = rtrim((string) config('services.ai.ollama.base_url', 'http://127.0.0.1:11434/v1'), '/');

            try {
                $response = Http::acceptJson()
                    ->timeout(1)
                    ->get($baseUrl.'/models');

                return $response->ok();
            } catch (Throwable) {
                return false;
            }
        });
    }
}
