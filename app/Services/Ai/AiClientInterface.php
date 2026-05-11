<?php

namespace App\Services\Ai;

interface AiClientInterface
{
    /**
     * @param  array<int, array<string, string>>  $messages
     * @return array{content:string,provider:string,model:string,latency_ms:int}
     */
    public function chat(array $messages): array;
}

