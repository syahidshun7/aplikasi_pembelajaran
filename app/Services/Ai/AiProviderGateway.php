<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderRateLimitedException;
use RuntimeException;
use Throwable;

class AiProviderGateway
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly OllamaClient $ollamaClient,
    ) {
    }

    /**
     * @param  array<int, array<string, string>>  $messages
     * @return array{content:string,provider_used:string,is_fallback:bool,latency_ms:int}
     */
    public function chat(array $messages): array
    {
        $primaryName = $this->normalizeProviderName((string) config('services.ai.primary', 'gemini'));
        $fallbackName = $this->normalizeProviderName((string) config('services.ai.fallback', 'none'));

        $primaryClient = $this->resolveClient($primaryName);

        try {
            $primaryResult = $primaryClient->chat($messages);

            return [
                'content' => $primaryResult['content'],
                'provider_used' => $primaryResult['provider'],
                'is_fallback' => false,
                'latency_ms' => (int) $primaryResult['latency_ms'],
            ];
        } catch (Throwable $primaryError) {
            if ($fallbackName === '' || $fallbackName === 'none' || $fallbackName === 'off' || $fallbackName === $primaryName) {
                if ($primaryError instanceof AiProviderRateLimitedException) {
                    throw new AiProviderRateLimitedException(
                        'Primary AI provider rate limited dan fallback AI tidak aktif.',
                        $primaryError,
                    );
                }

                throw new RuntimeException('Primary AI provider failed: '.$primaryError->getMessage(), previous: $primaryError);
            }

            $fallbackClient = $this->resolveClient($fallbackName);

            try {
                $fallbackResult = $fallbackClient->chat($messages);

                return [
                    'content' => $fallbackResult['content'],
                    'provider_used' => $fallbackResult['provider'],
                    'is_fallback' => true,
                    'latency_ms' => (int) $fallbackResult['latency_ms'],
                ];
            } catch (Throwable $fallbackError) {
                if ($primaryError instanceof AiProviderRateLimitedException && $fallbackError instanceof AiProviderRateLimitedException) {
                    throw new AiProviderRateLimitedException(
                        'Primary dan fallback AI provider sedang rate limited. Coba lagi beberapa menit lagi.',
                        $fallbackError,
                    );
                }

                throw new RuntimeException(
                    sprintf(
                        'Primary provider failed (%s). Fallback provider failed (%s).',
                        $primaryError->getMessage(),
                        $fallbackError->getMessage()
                    ),
                    previous: $fallbackError,
                );
            }
        }
    }

    private function resolveClient(string $provider): AiClientInterface
    {
        return match ($this->normalizeProviderName($provider)) {
            'gemini' => $this->geminiClient,
            'ollama' => $this->ollamaClient,
            default => throw new RuntimeException('Unsupported AI provider: '.$provider),
        };
    }

    private function normalizeProviderName(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
