<?php

namespace App\Services\Ai;

use App\Models\Rubric;
use App\Models\RubricDescription;
use App\Models\Submission;
use RuntimeException;

class SubmissionAiAdvisorService
{
    public function __construct(
        private readonly AiProviderGateway $providerGateway,
        private readonly AiResponseJsonParser $jsonParser,
        private readonly AiDataMaskingService $maskingService,
        private readonly SubmissionArtifactExtractorService $artifactExtractor,
        private readonly LocalArtifactPreprocessorService $localPreprocessor,
        private readonly SubmissionEvidencePreprocessorService $evidencePreprocessor,
        private readonly SubmissionQaDetectorService $qaDetector,
        private readonly SubmissionQaAiDetectorService $qaAiDetector,
    ) {
    }

    /**
     * @return array{
     *   advisor: array<string, mixed>,
     *   provider_used: string,
     *   is_fallback: bool,
     *   latency_ms: int
     * }
     */
    public function analyze(Submission $submission, ?string $advisorNote = null): array
    {
        $context = $this->prepareAnalysisContext($submission, $advisorNote, forAnalyze: true);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Kamu adalah AI advisor reviewer tugas coding/produk. Balas HANYA JSON valid tanpa markdown.',
            ],
            [
                'role' => 'user',
                'content' => $context['prompt'],
            ],
        ];

        $providerResult = $this->providerGateway->chat($messages);
        $decoded = $this->jsonParser->decode((string) ($providerResult['content'] ?? ''));

        if (! is_array($decoded)) {
            throw new RuntimeException('AI response is not decodable');
        }

        $advisor = $this->normalizeAdvisorPayload(
            decoded: $decoded,
            questTitle: $context['quest_title'],
            evidence: $context['evidence'],
            scoringSignals: $context['scoring_signals'] ?? [],
        );

        $mergedQaStats = $this->extractQaCountFromAdvisor(
            decoded: $decoded,
            providerResult: $providerResult,
            hasTaskBank: ! empty($context['task_bank_context']),
        );

        return [
            'advisor' => $advisor,
            'provider_used' => (string) $providerResult['provider_used'],
            'is_fallback' => (bool) $providerResult['is_fallback'],
            'latency_ms' => (int) $providerResult['latency_ms'],
            'artifact_source_flags' => $context['artifact']['source_flags'] ?? [],
            'artifact_warnings' => $context['artifact']['warnings'] ?? [],
            'used_local_preprocessor' => (bool) ($context['preprocessed']['used_local_preprocessor'] ?? false),
            'preprocessed_key_points' => $context['preprocessed']['key_points'] ?? [],
            'task_bank_context_present' => ! empty($context['task_bank_context']),
            'rubric_context_present' => ! empty($context['rubric_context']),
            'evidence_quality_score' => (float) ($context['evidence']['quality_score'] ?? 0),
            'evidence_quality_warnings' => $context['evidence']['quality_warnings'] ?? [],
            'confidence' => $context['evidence']['confidence'] ?? ['overall' => 0, 'rubric' => 0, 'task_bank' => 0],
            'merged_qa_stats' => $mergedQaStats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewPayload(Submission $submission, ?string $advisorNote = null): array
    {
        $context = $this->prepareAnalysisContext($submission, $advisorNote, forAnalyze: false);
        $stats = $this->buildPreviewStats($context);

        return [
            'quest' => [
                'title' => $context['quest_title'],
                'description' => $context['quest_description'],
                'difficulty' => $context['difficulty'],
            ],
            'artifact' => [
                'source_flags' => $context['artifact']['source_flags'] ?? [],
                'warnings' => $context['artifact']['warnings'] ?? [],
                'preview_text' => $this->clip((string) ($context['student_work'] ?? ''), 1500),
            ],
            'task_bank_context' => $context['task_bank_context'],
            'rubric_context' => $context['rubric_context'],
            'evidence' => $context['evidence'],
            'stats' => $stats,
            'advisor_note' => $context['advisor_note'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildPreviewStats(array $context): array
    {
        $taskBankQuestions = collect(data_get($context, 'task_bank_context.questions', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        $baselineQaStats = (array) data_get($context, 'qa_counts.baseline', [
            'question_total' => 0,
            'answered_total' => 0,
            'unanswered_total' => 0,
            'answer_completion_rate' => 0,
        ]);
        $aiQaStats = data_get($context, 'qa_counts.ai');
        $aiQaStats = is_array($aiQaStats) ? $aiQaStats : null;
        $hasTaskBankQuestionSet = $taskBankQuestions->count() > 0;

        $answeredCount = $taskBankQuestions
            ->filter(function ($question) {
                $answer = trim((string) ($question['user_answer'] ?? ''));
                return $answer !== '';
            })
            ->count();

        $baselineQuestionTotal = $hasTaskBankQuestionSet
            ? $taskBankQuestions->count()
            : (int) ($baselineQaStats['question_total'] ?? 0);

        $baselineAnsweredTotal = $hasTaskBankQuestionSet
            ? $answeredCount
            : (int) ($baselineQaStats['answered_total'] ?? 0);

        $baselineStats = [
            'question_total' => $baselineQuestionTotal,
            'answered_total' => $baselineAnsweredTotal,
            'unanswered_total' => max(0, $baselineQuestionTotal - $baselineAnsweredTotal),
            'answer_completion_rate' => $baselineQuestionTotal > 0
                ? round(($baselineAnsweredTotal / $baselineQuestionTotal) * 100, 2)
                : 0,
        ];

        if ($hasTaskBankQuestionSet) {
            $aiQaStats = null;
        }

        $questionTotal = (int) ($aiQaStats['question_total'] ?? $baselineStats['question_total']);
        $answeredTotal = (int) ($aiQaStats['answered_total'] ?? $baselineStats['answered_total']);
        $unansweredTotal = max(0, $questionTotal - $answeredTotal);

        $completionRate = $questionTotal > 0
            ? round(($answeredTotal / $questionTotal) * 100, 2)
            : 0;

        $countSource = $hasTaskBankQuestionSet
            ? 'task_bank'
            : ($aiQaStats !== null ? 'artifact_qa_ai' : ($questionTotal > 0 ? 'artifact_qa' : 'none'));

        $rubricCriteria = collect(data_get($context, 'rubric_context.criteria', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        $rubricLevels = collect(data_get($context, 'rubric_context.levels', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        $rubricMatrix = collect(data_get($context, 'rubric_context.matrix', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        $artifactWarnings = collect(data_get($context, 'artifact.warnings', []))
            ->map(fn ($item) => (string) $item)
            ->filter(fn ($item) => $item !== '')
            ->values();

        return [
            'artifact' => [
                'raw_combined_chars' => mb_strlen((string) data_get($context, 'artifact.combined_text', '')),
                'normalized_chars' => mb_strlen((string) data_get($context, 'student_work', '')),
                'source_flags_count' => count((array) data_get($context, 'artifact.source_flags', [])),
                'is_truncated' => $artifactWarnings->contains('ARTIFACT_TRUNCATED'),
            ],
            'task_bank' => [
                'present' => ! empty($context['task_bank_context']),
                'question_total' => $questionTotal,
                'answered_total' => $answeredTotal,
                'unanswered_total' => $unansweredTotal,
                'answer_completion_rate' => $completionRate,
                'count_source' => $countSource,
                'ai_count_confidence' => (int) ($aiQaStats['confidence'] ?? 0),
                'ai_count_notes' => (string) ($aiQaStats['notes'] ?? ''),
                'ai_provider_used' => (string) ($aiQaStats['provider_used'] ?? ''),
                'ai_provider_fallback' => (bool) ($aiQaStats['is_fallback'] ?? false),
            ],
            'rubric' => [
                'present' => ! empty($context['rubric_context']),
                'criteria_total' => $rubricCriteria->count(),
                'levels_total' => $rubricLevels->count(),
                'matrix_entries_total' => $rubricMatrix->count(),
            ],
            'evidence' => [
                'quality_score' => (float) data_get($context, 'evidence.quality_score', 0),
                'chunk_count' => (int) data_get($context, 'evidence.chunk_count', 0),
                'rubric_evidence_count' => count((array) data_get($context, 'evidence.rubric_evidence', [])),
                'task_bank_evidence_count' => count((array) data_get($context, 'evidence.task_bank_evidence', [])),
            ],
            'confidence' => [
                'overall' => (int) data_get($context, 'evidence.confidence.overall', 0),
                'rubric' => (int) data_get($context, 'evidence.confidence.rubric', 0),
                'task_bank' => (int) data_get($context, 'evidence.confidence.task_bank', 0),
            ],
            'warnings' => $artifactWarnings->all(),
            'advisor_note_present' => trim((string) data_get($context, 'advisor_note', '')) !== '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAnalysisContext(Submission $submission, ?string $advisorNote = null, bool $forAnalyze = true): array
    {
        $submission->loadMissing([
            'quest:id,title,description,difficulty,study_group_id,task_bank_id,rubric_id',
            'quest.rubric:id,title,description,max_score',
            'quest.rubric.criteria:id,rubric_id,name,weight,order',
            'quest.rubric.levels:id,rubric_id,level,label,score_value',
            'quest.taskBank:id,uuid,name,description,assessment_type,rubric_id',
            'quest.taskBank.rubric:id,title,description,max_score',
            'quest.taskBank.rubric.criteria:id,rubric_id,name,weight,order',
            'quest.taskBank.rubric.levels:id,rubric_id,level,label,score_value',
            'quest.taskBank.questions' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->select([
                        'id',
                        'uuid',
                        'task_bank_id',
                        'question_text',
                        'question_type',
                        'answer_key',
                        'weight',
                        'sort_order',
                    ]);
            },
            'user:id,name,username,email',
        ]);

        $questTitle = (string) ($submission->quest?->title ?? 'Unknown Quest');
        $questDescription = (string) ($submission->quest?->description ?? '');
        $difficulty = (string) ($submission->quest?->difficulty ?? 'C-Rank');
        $advisorNote = trim((string) $advisorNote);

        $artifact = $this->artifactExtractor->extract($submission);
        $preprocessed = $this->localPreprocessor->preprocess((string) $artifact['combined_text']);
        $taskBankContext = $this->buildTaskBankContext($submission);
        $rubricContext = $this->buildRubricContext($submission);
        $evidence = $this->evidencePreprocessor->preprocess(
            normalizedText: (string) $preprocessed['normalized_text'],
            rubricContext: $rubricContext,
            taskBankContext: $taskBankContext,
            artifactWarnings: $artifact['warnings'] ?? [],
            sourceFlags: $artifact['source_flags'] ?? [],
        );

        $normalizedText = (string) $preprocessed['normalized_text'];
        $baselineQa = $this->qaDetector->detect($normalizedText);
        $mergeIntoAdvisor = (bool) config('services.ai.qa_detector.merge_into_advisor_prompt', true);
        $skipAiCounter = $taskBankContext || ($forAnalyze && $mergeIntoAdvisor);
        $aiQaStats = $skipAiCounter
            ? null
            : $this->qaAiDetector->detect($normalizedText, $baselineQa);

        $scoringSignals = $this->buildScoringSignals(
            taskBankContext: $taskBankContext,
            baselineQaStats: $baselineQa,
            aiQaStats: $aiQaStats,
            evidence: $evidence,
            artifactWarnings: $artifact['warnings'] ?? [],
        );

        $studentWork = $this->maskingService->maskText((string) $preprocessed['normalized_text'], [
            (string) ($submission->user?->name ?? ''),
            (string) ($submission->user?->username ?? ''),
            (string) ($submission->user?->email ?? ''),
        ]);

        return [
            'quest_title' => $questTitle,
            'quest_description' => $questDescription,
            'difficulty' => $difficulty,
            'artifact' => $artifact,
            'preprocessed' => $preprocessed,
            'task_bank_context' => $taskBankContext,
            'rubric_context' => $rubricContext,
            'evidence' => $evidence,
            'scoring_signals' => $scoringSignals,
            'qa_counts' => [
                'baseline' => $baselineQa,
                'ai' => $aiQaStats,
            ],
            'student_work' => $studentWork,
            'advisor_note' => $advisorNote,
            'prompt' => $this->buildPrompt(
                questTitle: $questTitle,
                questDescription: $questDescription,
                difficulty: $difficulty,
                studentWork: $studentWork,
                sourceFlags: $artifact['source_flags'] ?? [],
                artifactWarnings: $artifact['warnings'] ?? [],
                preprocessingUsed: (bool) ($preprocessed['used_local_preprocessor'] ?? false),
                keyPoints: $preprocessed['key_points'] ?? [],
                taskBankContext: $taskBankContext,
                rubricContext: $rubricContext,
                evidenceContext: $evidence,
                scoringSignals: $scoringSignals,
                advisorNote: $advisorNote,
            ),
        ];
    }

    /**
     * @param  array<int, string>  $sourceFlags
     * @param  array<int, string>  $artifactWarnings
     * @param  array<int, string>  $keyPoints
     * @param  array<string, mixed>|null  $taskBankContext
     * @param  array<string, mixed>|null  $rubricContext
     * @param  array<string, mixed>  $evidenceContext
     * @param  array<string, mixed>  $scoringSignals
     */
    private function buildPrompt(
        string $questTitle,
        string $questDescription,
        string $difficulty,
        string $studentWork,
        array $sourceFlags,
        array $artifactWarnings,
        bool $preprocessingUsed,
        array $keyPoints,
        ?array $taskBankContext,
        ?array $rubricContext,
        array $evidenceContext,
        array $scoringSignals,
        string $advisorNote,
    ): string
    {
        return implode("\n", [
            'Analisis submission berikut dalam Bahasa Indonesia, ringkas dan actionable.',
            'Tugas:',
            '- title: '.$questTitle,
            '- description: '.$this->clip($questDescription, 1200),
            '- difficulty: '.$difficulty,
            '- source_flags: '.json_encode($sourceFlags, JSON_UNESCAPED_UNICODE),
            '- artifact_warnings: '.json_encode($artifactWarnings, JSON_UNESCAPED_UNICODE),
            '- local_preprocessing_used: '.($preprocessingUsed ? 'true' : 'false'),
            '- extracted_key_points: '.json_encode($keyPoints, JSON_UNESCAPED_UNICODE),
            '- task_bank_context: '.json_encode($taskBankContext, JSON_UNESCAPED_UNICODE),
            '- rubric_context: '.json_encode($rubricContext, JSON_UNESCAPED_UNICODE),
            '- evidence_context: '.json_encode($evidenceContext, JSON_UNESCAPED_UNICODE),
            '- scoring_signals: '.json_encode($scoringSignals, JSON_UNESCAPED_UNICODE),
            '- advisor_note_from_reviewer: '.($advisorNote !== '' ? $advisorNote : '[NONE]'),
            '',
            'Submission user (gabungan text + file extract, sudah dimasking):',
            $studentWork !== '' ? $studentWork : '[EMPTY_SUBMISSION]',
            '',
            'Keluarkan JSON dengan schema tepat:',
            '{',
            '  "summary": "string",',
            '  "strengths": ["string"],',
            '  "gaps": ["string"],',
            '  "risk_flags": ["string"],',
            '  "suggested_score_range": "min-max",',
            '  "suggested_feedback": "string",',
            '  "rubric_recommendations": [{"criteria_id": 0, "criteria_name": "string", "suggested_level_id": 0, "reason": "string"}],',
            '  "task_bank_findings": [{"question_uuid": "string", "question_type": "string", "result": "correct|incorrect|unclear", "reason": "string"}],',
            '  "qa_count": {"question_total": 0, "answered_total": 0, "notes": "string", "confidence": 0},',
            '  "confidence": {"overall": 0, "rubric": 0, "task_bank": 0, "notes": "string"}',
            '}',
            'Aturan:',
            '- Jangan menilai aspek personal.',
            '- Fokus kualitas hasil kerja dan kesesuaian tugas.',
            '- suggested_score_range wajib 1-100, contoh "70-85".',
            '- Jika context rubric tersedia, isi rubric_recommendations berbasis evidence_context.rubric_evidence.',
            '- Jika context task bank tersedia, isi task_bank_findings berdasarkan evidence_context.task_bank_evidence.',
            '- Wajib isi confidence secara konservatif. Jika evidence lemah, turunkan confidence.',
            '- Jika jawaban kosong banyak atau evidence lemah, turunkan suggested_score_range secara tegas.',
            '- Jika tidak ada task_bank_context, isi qa_count berdasarkan struktur laporan (deteksi pola soal/pertanyaan dan jawaban). Jika ada task_bank_context, isi qa_count sesuai jumlah soal yang tersedia.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function normalizeAdvisorPayload(array $decoded, string $questTitle, array $evidence, array $scoringSignals = []): array
    {
        $summary = trim((string) ($decoded['summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Analisis awal untuk quest '.$questTitle.' tersedia, namun respons AI tidak lengkap.';
        }

        $scoreRange = trim((string) ($decoded['suggested_score_range'] ?? ''));
        $min = 60;
        $max = 75;
        if (! preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})$/', $scoreRange, $matches)) {
            $scoreRange = '60-75';
        } else {
            $min = max(1, min(100, (int) $matches[1]));
            $max = max(1, min(100, (int) $matches[2]));
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }
        }

        $scoreCalibration = $this->calibrateScoreRange($min, $max, $scoringSignals);
        $scoreRange = $scoreCalibration['range'];

        $suggestedFeedback = trim((string) ($decoded['suggested_feedback'] ?? ''));
        if ($suggestedFeedback === '') {
            $suggestedFeedback = 'Tingkatkan kualitas penjelasan teknis dan validasi hasil akhir sebelum submit ulang.';
        }

        $fallbackOverall = (int) data_get($evidence, 'confidence.overall', 0);
        $fallbackRubric = (int) data_get($evidence, 'confidence.rubric', 0);
        $fallbackTaskBank = (int) data_get($evidence, 'confidence.task_bank', 0);

        $confidenceOverall = $this->normalizeConfidenceValue(data_get($decoded, 'confidence.overall'), $fallbackOverall);
        $confidenceRubric = $this->normalizeConfidenceValue(data_get($decoded, 'confidence.rubric'), $fallbackRubric);
        $confidenceTaskBank = $this->normalizeConfidenceValue(data_get($decoded, 'confidence.task_bank'), $fallbackTaskBank);
        $confidenceNotes = trim((string) data_get($decoded, 'confidence.notes', ''));
        if ($confidenceNotes === '') {
            $confidenceNotes = $fallbackOverall >= 70
                ? 'Evidence memadai untuk rekomendasi awal.'
                : 'Evidence terbatas, reviewer wajib verifikasi manual.';
        }

        return [
            'summary' => $summary,
            'strengths' => $this->normalizeStringList($decoded['strengths'] ?? []),
            'gaps' => $this->normalizeStringList($decoded['gaps'] ?? []),
            'risk_flags' => $this->normalizeStringList($decoded['risk_flags'] ?? []),
            'suggested_score_range' => $scoreRange,
            'suggested_feedback' => $suggestedFeedback,
            'rubric_recommendations' => $this->normalizeObjectList($decoded['rubric_recommendations'] ?? []),
            'task_bank_findings' => $this->normalizeObjectList($decoded['task_bank_findings'] ?? []),
            'score_calibration' => $scoreCalibration,
            'confidence' => [
                'overall' => $confidenceOverall,
                'rubric' => $confidenceRubric,
                'task_bank' => $confidenceTaskBank,
                'notes' => $confidenceNotes,
            ],
        ];
    }

    private function normalizeConfidenceValue(mixed $rawValue, int $fallback): int
    {
        $value = is_numeric($rawValue) ? (int) $rawValue : $fallback;
        return max(1, min(100, $value));
    }

    /**
     * @param  array<string, mixed>|null  $taskBankContext
     * @param  array<string, mixed>  $evidence
     * @param  array<int, string>  $artifactWarnings
     * @return array<string, mixed>
     */
    private function buildScoringSignals(?array $taskBankContext, array $baselineQaStats, ?array $aiQaStats, array $evidence, array $artifactWarnings): array
    {
        $taskBankQuestions = collect($taskBankContext['questions'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();

        if ($taskBankQuestions->isNotEmpty()) {
            $questionTotal = $taskBankQuestions->count();
            $answeredTotal = $taskBankQuestions
                ->filter(fn ($question) => $this->isMeaningfulAnswer((string) ($question['user_answer'] ?? '')))
                ->count();
            $countSource = 'task_bank';
        } elseif (is_array($aiQaStats) && (int) ($aiQaStats['question_total'] ?? 0) > 0) {
            $questionTotal = (int) ($aiQaStats['question_total'] ?? 0);
            $answeredTotal = (int) ($aiQaStats['answered_total'] ?? 0);
            $countSource = 'artifact_qa_ai';
        } else {
            $questionTotal = (int) ($baselineQaStats['question_total'] ?? 0);
            $answeredTotal = (int) ($baselineQaStats['answered_total'] ?? 0);
            $countSource = $questionTotal > 0 ? 'artifact_qa' : 'none';
        }

        $questionTotal = max(0, $questionTotal);
        $answeredTotal = max(0, min($questionTotal, $answeredTotal));
        $unansweredTotal = max(0, $questionTotal - $answeredTotal);
        $completionRate = $questionTotal > 0
            ? round(($answeredTotal / $questionTotal) * 100, 2)
            : 0;

        return [
            'question_total' => $questionTotal,
            'answered_total' => $answeredTotal,
            'unanswered_total' => $unansweredTotal,
            'answer_completion_rate' => $completionRate,
            'count_source' => $countSource,
            'evidence_quality_score' => (float) ($evidence['quality_score'] ?? 0),
            'evidence_quality_warnings' => array_values(array_unique(array_map('strval', $evidence['quality_warnings'] ?? []))),
            'artifact_warnings' => array_values(array_unique(array_map('strval', $artifactWarnings))),
        ];
    }

    /**
     * @param  array<string, mixed>  $signals
     * @return array{range:string,penalty_points:int,reasons:array<int, string>,original_range:string}
     */
    private function calibrateScoreRange(int $min, int $max, array $signals): array
    {
        $originalRange = $min.'-'.$max;
        $penalty = 0;
        $reasons = [];

        $questionTotal = max(0, (int) ($signals['question_total'] ?? 0));
        $unansweredTotal = max(0, (int) ($signals['unanswered_total'] ?? 0));
        $completionRate = max(0, min(100, (float) ($signals['answer_completion_rate'] ?? 0)));

        if ($questionTotal > 0 && $unansweredTotal > 0) {
            $basePenalty = min(40, $unansweredTotal * 8);
            $penalty += $basePenalty;
            $reasons[] = 'UNANSWERED_ITEMS_'.$unansweredTotal;

            if ($completionRate < 80) {
                $penalty += 8;
                $reasons[] = 'LOW_COMPLETION_UNDER_80';
            }
            if ($completionRate < 60) {
                $penalty += 8;
                $reasons[] = 'LOW_COMPLETION_UNDER_60';
            }
        }

        $qualityScore = max(0, min(1, (float) ($signals['evidence_quality_score'] ?? 0)));
        if ($qualityScore <= 0.5) {
            $penalty += 10;
            $reasons[] = 'EVIDENCE_QUALITY_BELOW_0_50';
        }
        if ($qualityScore <= 0.35) {
            $penalty += 10;
            $reasons[] = 'EVIDENCE_QUALITY_BELOW_0_35';
        }
        if ($qualityScore <= 0.2) {
            $penalty += 10;
            $reasons[] = 'EVIDENCE_QUALITY_BELOW_0_20';
        }

        $warningSet = array_values(array_unique(array_merge(
            array_map('strval', $signals['evidence_quality_warnings'] ?? []),
            array_map('strval', $signals['artifact_warnings'] ?? []),
        )));

        if (in_array('TEXT_TOO_SHORT_FOR_RELIABLE_SCORING', $warningSet, true)) {
            $penalty += 10;
            $reasons[] = 'TEXT_TOO_SHORT';
        }
        if (in_array('NO_TEXT_SOURCE_DETECTED', $warningSet, true) || in_array('PDF_TEXT_EMPTY_OR_UNREADABLE', $warningSet, true)) {
            $penalty += 15;
            $reasons[] = 'SOURCE_TEXT_UNREADABLE';
        }

        $penalty = max(0, min(60, $penalty));
        $adjustedMin = max(1, min(100, $min - $penalty));
        $adjustedMax = max(1, min(100, $max - $penalty));
        if ($adjustedMin > $adjustedMax) {
            [$adjustedMin, $adjustedMax] = [$adjustedMax, $adjustedMin];
        }

        return [
            'range' => $adjustedMin.'-'.$adjustedMax,
            'penalty_points' => $penalty,
            'reasons' => $reasons,
            'original_range' => $originalRange,
        ];
    }

    private function isMeaningfulAnswer(string $answer): bool
    {
        $answer = trim($answer);
        if ($answer === '') {
            return false;
        }

        return preg_match('/^(n\/?a|tidak\s+ada|belum\s+diisi|kosong|-)$/iu', $answer) !== 1;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $providerResult
     * @return array<string, mixed>|null
     */
    private function extractQaCountFromAdvisor(array $decoded, array $providerResult, bool $hasTaskBank): ?array
    {
        if ($hasTaskBank) {
            return null;
        }

        if (! (bool) config('services.ai.qa_detector.merge_into_advisor_prompt', true)) {
            return null;
        }

        $rawQa = data_get($decoded, 'qa_count');
        if (! is_array($rawQa)) {
            return null;
        }

        $questionTotal = max(0, (int) ($rawQa['question_total'] ?? 0));
        $answeredTotal = max(0, min($questionTotal, (int) ($rawQa['answered_total'] ?? 0)));

        if ($questionTotal <= 0) {
            return null;
        }

        $completion = $questionTotal > 0
            ? round(($answeredTotal / $questionTotal) * 100, 2)
            : 0;

        return [
            'question_total' => $questionTotal,
            'answered_total' => $answeredTotal,
            'unanswered_total' => max(0, $questionTotal - $answeredTotal),
            'answer_completion_rate' => $completion,
            'notes' => trim((string) ($rawQa['notes'] ?? '')),
            'confidence' => max(0, min(100, (int) ($rawQa['confidence'] ?? 0))),
            'provider_used' => (string) ($providerResult['provider_used'] ?? ''),
            'is_fallback' => (bool) ($providerResult['is_fallback'] ?? false),
            'latency_ms' => (int) ($providerResult['latency_ms'] ?? 0),
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    private function normalizeObjectList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_array($item))
            ->map(function ($item) {
                return collect($item)
                    ->map(function ($cell) {
                        if (is_array($cell)) {
                            return $cell;
                        }

                        return is_scalar($cell) || $cell === null ? (string) $cell : null;
                    })
                    ->filter(fn ($cell) => $cell !== null)
                    ->all();
            })
            ->filter(fn ($item) => ! empty($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildTaskBankContext(Submission $submission): ?array
    {
        $taskBank = $submission->quest?->taskBank;
        if (! $taskBank) {
            return null;
        }

        $scoresDetail = is_array($submission->scores_detail) ? $submission->scores_detail : [];
        $answers = is_array($scoresDetail['answers'] ?? null) ? $scoresDetail['answers'] : [];

        $questions = collect($taskBank->questions ?? [])
            ->take(60)
            ->map(function ($question) use ($answers) {
                $questionUuid = (string) ($question->uuid ?? '');
                $userAnswer = $answers[$questionUuid] ?? null;

                return [
                    'uuid' => $questionUuid,
                    'question_type' => (string) ($question->question_type ?? ''),
                    'question_text' => $this->clip((string) ($question->question_text ?? ''), 700),
                    'weight' => (int) ($question->weight ?? 0),
                    'answer_key' => $this->clip((string) ($question->answer_key ?? ''), 200),
                    'user_answer' => is_scalar($userAnswer) ? $this->clip((string) $userAnswer, 400) : (is_array($userAnswer) ? json_encode($userAnswer, JSON_UNESCAPED_UNICODE) : ''),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $taskBank->id,
            'uuid' => (string) ($taskBank->uuid ?? ''),
            'name' => (string) ($taskBank->name ?? ''),
            'description' => $this->clip((string) ($taskBank->description ?? ''), 1200),
            'assessment_type' => (string) ($taskBank->assessment_type ?? ''),
            'question_count' => count($questions),
            'questions' => $questions,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRubricContext(Submission $submission): ?array
    {
        $rubric = $submission->quest?->rubric ?: $submission->quest?->taskBank?->rubric;

        if (! $rubric instanceof Rubric) {
            return null;
        }

        $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($id) => (int) $id)->all();
        $descriptions = count($criteriaIds)
            ? RubricDescription::query()->whereIn('criteria_id', $criteriaIds)->get(['criteria_id', 'level_id', 'description'])
            : collect();

        $matrix = [];
        foreach ($descriptions as $description) {
            $matrix[] = [
                'criteria_id' => (int) $description->criteria_id,
                'level_id' => (int) $description->level_id,
                'description' => $this->clip((string) $description->description, 500),
            ];
        }

        return [
            'id' => (int) $rubric->id,
            'title' => (string) ($rubric->title ?? ''),
            'description' => $this->clip((string) ($rubric->description ?? ''), 1000),
            'max_score' => (float) ($rubric->max_score ?? 0),
            'criteria' => $rubric->criteria->map(fn ($criterion) => [
                'id' => (int) $criterion->id,
                'name' => (string) ($criterion->name ?? ''),
                'weight' => (float) ($criterion->weight ?? 0),
                'order' => (int) ($criterion->order ?? 0),
            ])->values()->all(),
            'levels' => $rubric->levels->map(fn ($level) => [
                'id' => (int) $level->id,
                'level' => (int) ($level->level ?? 0),
                'label' => (string) ($level->label ?? ''),
                'score_value' => (float) ($level->score_value ?? 0),
            ])->values()->all(),
            'matrix' => $matrix,
        ];
    }

    private function clip(string $value, int $maxChars): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= $maxChars) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $maxChars);
    }
}
