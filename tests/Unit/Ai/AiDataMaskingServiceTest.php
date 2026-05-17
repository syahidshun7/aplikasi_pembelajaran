<?php

use App\Services\Ai\AiDataMaskingService;

uses(Tests\TestCase::class);

test('ai data masking service masks email url id and named tokens', function () {
    $service = new AiDataMaskingService();

    $result = $service->maskText(
        'Nama: Budi, email budi@example.com, link https://example.com, user_id=123456789.',
        ['Budi']
    );

    expect($result)->toContain('[MASKED_NAME]');
    expect($result)->toContain('[MASKED_EMAIL]');
    expect($result)->toContain('[MASKED_URL]');
    expect($result)->toContain('[MASKED_ID]');
});
