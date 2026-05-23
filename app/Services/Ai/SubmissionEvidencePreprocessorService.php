<?php

namespace App\Services\Ai;

class SubmissionEvidencePreprocessorService
{
    private array $stopwords = [
        'yang', 'dan', 'atau', 'untuk', 'dengan', 'dari', 'pada', 'adalah', 'ini', 'itu', 'sebagai',
        'dalam', 'ke', 'di', 'the', 'and', 'for', 'with', 'from', 'that', 'this', 'code', 'laravel',
    ];

    /**
     * @param  array<string, mixed>|null  $rubricContext
     * @param  array<string, mixed>|null  $taskBankContext
     * @param  array<int, string>  $artifactWarnings
     * @param  array<int, string>  $sourceFlags
     * @return array<string, mixed>
     */
    public function preprocess(
        string $normalizedText,
        ?array $rubricContext,
        ?array $taskBankContext,
        array $artifactWarnings,
        array $sourceFlags,
    ): array {
        $chunks = $this->splitIntoChunks($normalizedText);
        $taskBankSectionsByUuid = $this->extractTaskBankAnswerSections($normalizedText);
        $textLength = mb_strlen(trim($normalizedText));

        $qualityScore = $this->computeQualityScore($textLength, $artifactWarnings, $sourceFlags);
        $qualityWarnings = $this->buildQualityWarnings($textLength, $artifactWarnings, count($chunks));

        $rubricEvidence = $this->buildRubricEvidence($chunks, $rubricContext);
        $taskBankEvidence = $this->buildTaskBankEvidence($chunks, $taskBankContext, $taskBankSectionsByUuid);

        $rubricConfidence = $this->averageConfidence($rubricEvidence);
        $taskBankConfidence = $this->averageConfidence($taskBankEvidence);

        $overallConfidence = $this->computeOverallConfidence(
            qualityScore: $qualityScore,
            rubricConfidence: $rubricConfidence,
            taskBankConfidence: $taskBankConfidence,
            hasRubricEvidence: ! empty($rubricEvidence),
            hasTaskBankEvidence: ! empty($taskBankEvidence),
        );

        return [
            'quality_score' => $qualityScore,
            'quality_warnings' => $qualityWarnings,
            'chunk_count' => count($chunks),
            'rubric_evidence' => $rubricEvidence,
            'task_bank_evidence' => $taskBankEvidence,
            'confidence' => [
                'overall' => $overallConfidence,
                'rubric' => $rubricConfidence,
                'task_bank' => $taskBankConfidence,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRubricEvidence(array $chunks, ?array $rubricContext): array
    {
        if (! is_array($rubricContext) || ! is_array($rubricContext['criteria'] ?? null)) {
            return [];
        }

        $matrix = collect($rubricContext['matrix'] ?? [])->filter(fn ($item) => is_array($item));

        $evidence = [];
        foreach ($rubricContext['criteria'] as $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $criterionId = (int) ($criterion['id'] ?? 0);
            $criterionName = (string) ($criterion['name'] ?? '');

            $criterionMatrixDescriptions = $matrix
                ->filter(fn ($row) => (int) ($row['criteria_id'] ?? 0) === $criterionId)
                ->pluck('description')
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->values()
                ->all();

            $keywords = $this->extractKeywords(implode(' ', array_merge([$criterionName], $criterionMatrixDescriptions)), 14);
            $match = $this->matchChunksByKeywords($chunks, $keywords, 2);

            $evidence[] = [
                'criteria_id' => $criterionId,
                'criteria_name' => $criterionName,
                'keywords' => $keywords,
                'snippets' => $match['snippets'],
                'match_count' => $match['match_count'],
                'confidence' => $this->scoreConfidence($match['match_count'], count($keywords), ! empty($match['snippets'])),
            ];
        }

        return $evidence;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTaskBankEvidence(array $chunks, ?array $taskBankContext, array $taskBankSectionsByUuid = []): array
    {
        if (! is_array($taskBankContext) || ! is_array($taskBankContext['questions'] ?? null)) {
            return [];
        }

        $evidence = [];
        foreach ($taskBankContext['questions'] as $question) {
            if (! is_array($question)) {
                continue;
            }

            $questionText = (string) ($question['question_text'] ?? '');
            $userAnswer = trim((string) ($question['user_answer'] ?? ''));
            $questionUuid = trim((string) ($question['uuid'] ?? ''));
            $keywords = $this->extractKeywords($questionText, 12);
            $strictSection = trim((string) ($taskBankSectionsByUuid[$questionUuid] ?? ''));
            $match = $this->matchChunksByKeywords($chunks, $keywords, 2);

            $snippets = $match['snippets'];
            $matchCount = (int) ($match['match_count'] ?? 0);

            if ($strictSection !== '') {
                $strictMatch = $this->matchChunksByKeywords([$strictSection], $keywords, 1);
                $strictSnippets = $this->buildStrictSnippetsFromSection($strictSection);

                $snippets = ! empty($strictSnippets)
                    ? $strictSnippets
                    : [$this->clip($strictSection, 320)];
                $matchCount = max((int) ($strictMatch['match_count'] ?? 0), $userAnswer !== '' ? 1 : 0);
            }

            if (empty($snippets) && $userAnswer !== '') {
                $syntheticSnippet = $this->clip(
                    'Q: '.trim($questionText).' | A: '.$userAnswer,
                    320
                );
                if ($syntheticSnippet !== '') {
                    $snippets = [$syntheticSnippet];
                    $matchCount = max(1, $matchCount);
                }
            }

            $baseConfidence = $this->scoreConfidence($matchCount, count($keywords), ! empty($snippets));
            if ($userAnswer !== '') {
                $baseConfidence = min(100, $baseConfidence + 10);
            }

            $evidence[] = [
                'question_uuid' => $questionUuid,
                'question_type' => (string) ($question['question_type'] ?? ''),
                'keywords' => $keywords,
                'snippets' => $snippets,
                'match_count' => $matchCount,
                'has_user_answer' => $userAnswer !== '',
                'confidence' => $baseConfidence,
            ];
        }

        return $evidence;
    }

    /**
     * @return array{snippets:array<int,string>,match_count:int}
     */
    private function matchChunksByKeywords(array $chunks, array $keywords, int $limit): array
    {
        if (empty($chunks) || empty($keywords)) {
            return ['snippets' => [], 'match_count' => 0];
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $lower = mb_strtolower($chunk);
            $hits = 0;

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($lower, $keyword)) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $scored[] = ['chunk' => $chunk, 'hits' => $hits];
            }
        }

        usort($scored, fn ($a, $b) => $b['hits'] <=> $a['hits']);
        $snippets = collect($scored)
            ->take($limit)
            ->pluck('chunk')
            ->map(fn ($item) => $this->clip((string) $item, 320))
            ->values()
            ->all();

        return [
            'snippets' => $snippets,
            'match_count' => count($scored),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $text, int $max): array
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $keywords = collect($tokens)
            ->filter(fn ($token) => mb_strlen($token) >= 4)
            ->reject(fn ($token) => in_array($token, $this->stopwords, true))
            ->unique()
            ->take($max)
            ->values()
            ->all();

        return $keywords;
    }

    /**
     * @return array<int, string>
     */
    private function splitIntoChunks(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
        if ($normalized === '' || $normalized === '[NO_READABLE_ARTIFACT_FOUND]') {
            return [];
        }

        $rawParts = preg_split('/(?<=[.!?])\s+|\s*\n+\s*/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $chunks = [];
        $buffer = '';
        foreach ($rawParts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (mb_strlen($buffer.' '.$part) <= 350) {
                $buffer = trim($buffer.' '.$part);
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }
            $buffer = $part;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return collect($chunks)
            ->map(fn ($item) => $this->clip((string) $item, 350))
            ->take(120)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function averageConfidence(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $avg = collect($rows)->avg(fn ($row) => (int) ($row['confidence'] ?? 0));
        return (int) round((float) $avg);
    }

    private function scoreConfidence(int $matchCount, int $keywordCount, bool $hasSnippet): int
    {
        if ($keywordCount <= 0) {
            return 35;
        }

        $coverage = min(1.0, $matchCount / max(1, $keywordCount));
        $score = (int) round(35 + ($coverage * 45) + ($hasSnippet ? 20 : 0));

        return max(1, min(100, $score));
    }

    private function computeOverallConfidence(
        float $qualityScore,
        int $rubricConfidence,
        int $taskBankConfidence,
        bool $hasRubricEvidence,
        bool $hasTaskBankEvidence,
    ): int {
        $quality = max(1, min(100, (int) round($qualityScore * 100)));

        if ($hasTaskBankEvidence && ! $hasRubricEvidence) {
            $overall = (int) round(($quality * 0.35) + ($taskBankConfidence * 0.65));
            return max(1, min(100, $overall));
        }

        if ($hasRubricEvidence && ! $hasTaskBankEvidence) {
            $overall = (int) round(($quality * 0.35) + ($rubricConfidence * 0.65));
            return max(1, min(100, $overall));
        }

        if ($hasRubricEvidence && $hasTaskBankEvidence) {
            $overall = (int) round(($quality * 0.30) + ($rubricConfidence * 0.35) + ($taskBankConfidence * 0.35));
            return max(1, min(100, $overall));
        }

        return $quality;
    }

    /**
     * @return array<string, string>
     */
    private function extractTaskBankAnswerSections(string $normalizedText): array
    {
        $markerPos = mb_stripos($normalizedText, '[TASK_BANK_ANSWERS]');
        if ($markerPos === false) {
            return [];
        }

        $block = trim((string) mb_substr($normalizedText, $markerPos));
        if ($block === '') {
            return [];
        }

        preg_match_all(
            '/Q\d+\s*\|\s*uuid=([^|\n]+)\s*\|[^\n]*\nQUESTION:.*?(?=(?:\n\s*Q\d+\s*\|\s*uuid=)|\z)/si',
            $block,
            $matches,
            PREG_SET_ORDER
        );

        $sections = [];
        foreach ($matches as $match) {
            $uuid = trim((string) ($match[1] ?? ''));
            $section = trim((string) ($match[0] ?? ''));
            if ($uuid === '' || $section === '') {
                continue;
            }

            $sections[$uuid] = $section;
        }

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    private function buildStrictSnippetsFromSection(string $section): array
    {
        $section = trim($section);
        if ($section === '') {
            return [];
        }

        $snippets = [];

        if (preg_match('/QUESTION:\s*(.+?)(?:\nANSWER:|$)/si', $section, $questionMatch)) {
            $question = trim((string) ($questionMatch[1] ?? ''));
            if ($question !== '') {
                $snippets[] = $this->clip('QUESTION: '.$question, 320);
            }
        }

        if (preg_match('/ANSWER:\s*(.+)$/si', $section, $answerMatch)) {
            $answer = trim((string) ($answerMatch[1] ?? ''));
            if ($answer !== '') {
                $snippets[] = $this->clip('ANSWER: '.$answer, 320);
            }
        }

        if (empty($snippets)) {
            $snippets[] = $this->clip($section, 320);
        }

        return array_values(array_unique(array_filter($snippets, fn ($value) => trim((string) $value) !== '')));
    }

    /**
     * @param  array<int, string>  $artifactWarnings
     * @param  array<int, string>  $sourceFlags
     */
    private function computeQualityScore(int $textLength, array $artifactWarnings, array $sourceFlags): float
    {
        $score = 0.25;

        if ($textLength >= 600) {
            $score += 0.35;
        } elseif ($textLength >= 250) {
            $score += 0.2;
        } elseif ($textLength >= 100) {
            $score += 0.1;
        }

        if (in_array('pdf', $sourceFlags, true) || in_array('text', $sourceFlags, true)) {
            $score += 0.2;
        }

        if (empty($artifactWarnings)) {
            $score += 0.2;
        } else {
            $score -= min(0.3, count($artifactWarnings) * 0.1);
        }

        return max(0.1, min(1.0, round($score, 2)));
    }

    /**
     * @param  array<int, string>  $artifactWarnings
     * @return array<int, string>
     */
    private function buildQualityWarnings(int $textLength, array $artifactWarnings, int $chunkCount): array
    {
        $warnings = [];

        if ($textLength < 120) {
            $warnings[] = 'TEXT_TOO_SHORT_FOR_RELIABLE_SCORING';
        }
        if ($chunkCount <= 2) {
            $warnings[] = 'EVIDENCE_CHUNKS_MINIMAL';
        }

        foreach ($artifactWarnings as $warning) {
            $warnings[] = (string) $warning;
        }

        return array_values(array_unique($warnings));
    }

    private function clip(string $value, int $maxChars): string
    {
        $trimmed = trim($value);
        if (mb_strlen($trimmed) <= $maxChars) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $maxChars);
    }
}
