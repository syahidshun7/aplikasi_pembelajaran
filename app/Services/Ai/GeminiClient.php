<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient extends AbstractOpenAiCompatibleClient
{
    public function __construct(
        private readonly ?string $apiKey,
        string $baseUrl,
        string $model,
        int $timeoutMs,
        int $retryCount,
    ) {
        parent::__construct('gemini', $baseUrl, $model, $timeoutMs, $retryCount);
    }

    protected function newRequest()
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY is missing');
        }

        return Http::acceptJson()
            ->asJson()
            ->withOptions([
                'verify' => (bool) config('services.ai.verify_ssl', true),
            ])
            ->withToken((string) $this->apiKey);
    }
}
