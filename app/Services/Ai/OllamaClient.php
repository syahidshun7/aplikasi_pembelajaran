<?php

namespace App\Services\Ai;

class OllamaClient extends AbstractOpenAiCompatibleClient
{
    public function __construct(
        string $baseUrl,
        string $model,
        int $timeoutMs,
        int $retryCount,
    ) {
        parent::__construct('ollama', $baseUrl, $model, $timeoutMs, $retryCount);
    }
}

