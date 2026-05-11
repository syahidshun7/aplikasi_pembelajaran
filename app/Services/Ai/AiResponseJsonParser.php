<?php

namespace App\Services\Ai;

class AiResponseJsonParser
{
    public function decode(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        $direct = $this->tryDecode($trimmed);
        if ($direct !== null) {
            return $direct;
        }

        $withoutFence = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $trimmed) ?? $trimmed;
        $decodedFence = $this->tryDecode(trim($withoutFence));
        if ($decodedFence !== null) {
            return $decodedFence;
        }

        $objectChunk = $this->extractFirstJsonObject($trimmed);
        if ($objectChunk !== null) {
            $decodedChunk = $this->tryDecode($objectChunk);
            if ($decodedChunk !== null) {
                return $decodedChunk;
            }
        }

        return [];
    }

    private function tryDecode(string $candidate): ?array
    {
        try {
            $decoded = json_decode($candidate, true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    private function extractFirstJsonObject(string $value): ?string
    {
        $start = strpos($value, '{');
        if ($start === false) {
            return null;
        }

        $length = strlen($value);
        $depth = 0;

        for ($index = $start; $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($value, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }
}

