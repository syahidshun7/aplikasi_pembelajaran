<?php

namespace App\Services;

use App\Models\Rubric;

class RubricScoringService
{
    /**
     * Score formula (per criteria):
     * score = (selected_level_score / max_level_score) * criteria_weight
     *
     * @param  array<int,int>  $selectedLevelByCriteriaId  [criteria_id => level_id]
     * @return array{total: float, max_score: float, max_level_score: float, breakdown: array<int,array<string,mixed>>}
     */
    public function calculate(Rubric $rubric, array $selectedLevelByCriteriaId): array
    {
        $rubric->loadMissing(['criteria', 'levels']);

        $maxLevelScore = (float) ($rubric->levels->max('score_value') ?? 0);
        $maxScore = (float) ($rubric->criteria->sum('weight') ?? 0);

        if ($maxLevelScore <= 0) {
            return [
                'total' => 0.0,
                'max_score' => $maxScore,
                'max_level_score' => 0.0,
                'breakdown' => [],
            ];
        }

        $levelsById = $rubric->levels->keyBy('id');
        $total = 0.0;
        $breakdown = [];

        foreach ($rubric->criteria as $criterion) {
            $criterionId = (int) $criterion->id;
            $weight = (float) $criterion->weight;
            $levelId = (int) ($selectedLevelByCriteriaId[$criterionId] ?? 0);
            $selectedLevel = $levelsById->get($levelId);
            $selectedScore = $selectedLevel ? (float) $selectedLevel->score_value : 0.0;
            $score = ($selectedScore / $maxLevelScore) * $weight;

            $breakdown[] = [
                'criteria_id' => $criterionId,
                'criteria_name' => $criterion->name,
                'weight' => $weight,
                'selected_level_id' => $levelId ?: null,
                'selected_level_score' => $selectedScore,
                'score' => $score,
            ];

            $total += $score;
        }

        return [
            'total' => $total,
            'max_score' => $maxScore,
            'max_level_score' => $maxLevelScore,
            'breakdown' => $breakdown,
        ];
    }

    public function calculateMaxScore(Rubric $rubric): float
    {
        $rubric->loadMissing('criteria');
        return (float) ($rubric->criteria->sum('weight') ?? 0);
    }
}

