<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AiProviderRateLimitedException extends RuntimeException
{
    public function __construct(
        string $message = 'AI provider sedang rate limited. Coba lagi beberapa menit lagi.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $previous);
    }
}
