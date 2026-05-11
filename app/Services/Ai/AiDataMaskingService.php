<?php

namespace App\Services\Ai;

class AiDataMaskingService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.ai.masking_enabled', true);
    }

    /**
     * @param  array<int, string>  $namedTokens
     */
    public function maskText(string $text, array $namedTokens = []): string
    {
        if (! $this->isEnabled()) {
            return $text;
        }

        $masked = $text;

        $patterns = [
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[MASKED_EMAIL]',
            '/https?:\/\/[^\s]+/i' => '[MASKED_URL]',
            '/\b(?:id|user_id|student_id|mentor_id)\s*[:=]\s*[A-Z0-9_-]+\b/i' => 'id=[MASKED_ID]',
            '/\b\d{6,}\b/' => '[MASKED_ID]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $masked = preg_replace($pattern, $replacement, $masked) ?? $masked;
        }

        foreach ($namedTokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            $masked = str_ireplace($token, '[MASKED_NAME]', $masked);
        }

        return $masked;
    }
}
