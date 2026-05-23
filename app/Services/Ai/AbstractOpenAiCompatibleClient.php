<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

abstract class AbstractOpenAiCompatibleClient implements AiClientInterface
{
    public function __construct(
        protected readonly string $provider,
        protected readonly string $baseUrl,
        protected readonly string $model,
        protected readonly int $timeoutMs,
        protected readonly int $retryCount,
    ) {
    }

    /**
     * @param  array<int, array<string, string>>  $messages
     * @return array{content:string,provider:string,model:string,latency_ms:int}
     */
    public function chat(array $messages): array
    {
        $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';
        $attempts = max(1, $this->retryCount + 1);
        $lastError = null;
        $useJsonFormat = true;
        $temperature = (float) config('services.ai.temperature', 0.0);
        $temperature = max(0.0, min(1.0, $temperature));
        $allowResponseFormatFallback = $this->provider !== 'gemini';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $startedAt = microtime(true);

            try {
                $payload = [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                ];
                if ($useJsonFormat) {
                    $payload['response_format'] = ['type' => 'json_object'];
                }

                $response = $this->newRequest()
                    ->timeout(max(1, (int) ceil($this->timeoutMs / 1000)))
                    ->post($endpoint, $payload);

                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

                if ($response->failed()) {
                    if ($allowResponseFormatFallback && $useJsonFormat && in_array($response->status(), [400, 404, 415, 422], true)) {
                        $useJsonFormat = false;
                        continue;
                    }

                    throw new RuntimeException(sprintf(
                        '%s request failed with status %d',
                        strtoupper($this->provider),
                        (int) $response->status()
                    ));
                }

                $content = $this->extractContent($response->json());
                if (trim($content) === '') {
                    throw new RuntimeException(strtoupper($this->provider).' returned empty completion content');
                }

                return [
                    'content' => $content,
                    'provider' => $this->provider,
                    'model' => $this->model,
                    'latency_ms' => $latencyMs,
                ];
            } catch (Throwable $exception) {
                $lastError = $exception;
                if ($attempt >= $attempts) {
                    break;
                }

                usleep(200000);
            }
        }

        if ($lastError instanceof ConnectionException) {
            throw new RuntimeException(strtoupper($this->provider).' connection error: '.$lastError->getMessage(), previous: $lastError);
        }

        throw new RuntimeException(strtoupper($this->provider).' failed: '.($lastError?->getMessage() ?? 'unknown error'), previous: $lastError);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function extractContent(array $json): string
    {
        $content = data_get($json, 'choices.0.message.content');

        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $parts = collect($content)
                ->map(function ($part) {
                    if (is_string($part)) {
                        return $part;
                    }

                    if (is_array($part)) {
                        return (string) ($part['text'] ?? $part['content'] ?? '');
                    }

                    return '';
                })
                ->filter(fn ($part) => trim($part) !== '')
                ->values()
                ->all();

            if (! empty($parts)) {
                return implode("\n", $parts);
            }
        }

        return '';
    }

    protected function newRequest()
    {
        return Http::acceptJson()
            ->asJson()
            ->withOptions([
                'verify' => (bool) config('services.ai.verify_ssl', true),
            ]);
    }
}
