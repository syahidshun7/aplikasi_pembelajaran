<?php

use App\Services\Ai\AiResponseJsonParser;

test('ai response json parser can parse fenced or noisy json output', function () {
    $parser = new AiResponseJsonParser();

    $content = "Berikut hasilnya:\n```json\n{\"summary\":\"ok\",\"suggested_score_range\":\"70-80\"}\n```";
    $decoded = $parser->decode($content);

    expect((string) ($decoded['summary'] ?? ''))->toBe('ok');
    expect((string) ($decoded['suggested_score_range'] ?? ''))->toBe('70-80');
});

