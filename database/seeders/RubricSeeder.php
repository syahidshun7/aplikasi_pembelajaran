<?php

namespace Database\Seeders;

use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RubricSeeder extends Seeder
{
    public function run(): void
    {
        $mentor = User::query()->where('role', User::ROLE_MENTOR)->first();

        if (! $mentor) {
            $mentor = User::create([
                'name' => 'Mentor PPLG',
                'email' => 'mentor@guild.com',
                'password' => Hash::make('12345678'),
                'role' => User::ROLE_MENTOR,
            ]);
        }

        $rubric = Rubric::query()->firstOrCreate(
            ['title' => 'Essay Rubric (Sample)', 'mentor_id' => $mentor->id],
            ['description' => 'Sample rubric for essay grading.', 'max_score' => 100]
        );

        // Ensure levels exist.
        $levels = [
            ['level' => 1, 'label' => 'Poor', 'score_value' => 1],
            ['level' => 2, 'label' => 'Fair', 'score_value' => 2],
            ['level' => 3, 'label' => 'Good', 'score_value' => 3],
            ['level' => 4, 'label' => 'Excellent', 'score_value' => 4],
        ];

        foreach ($levels as $l) {
            RubricLevel::query()->firstOrCreate(
                ['rubric_id' => $rubric->id, 'level' => $l['level']],
                ['label' => $l['label'], 'score_value' => $l['score_value']]
            );
        }

        $criteria = [
            ['name' => 'Concept', 'weight' => 25, 'order' => 1],
            ['name' => 'Analysis', 'weight' => 25, 'order' => 2],
            ['name' => 'Structure', 'weight' => 25, 'order' => 3],
            ['name' => 'Mechanics', 'weight' => 25, 'order' => 4],
        ];

        foreach ($criteria as $c) {
            RubricCriterion::query()->firstOrCreate(
                ['rubric_id' => $rubric->id, 'order' => $c['order']],
                ['name' => $c['name'], 'weight' => $c['weight']]
            );
        }

        $rubric->load(['criteria', 'levels']);

        // Seed a handful of matrix descriptions (not necessarily full).
        $cells = [
            'Concept' => [
                'Poor' => 'Idea unclear / off-topic.',
                'Good' => 'Idea mostly clear and relevant.',
                'Excellent' => 'Idea is clear, original, and strongly relevant.',
            ],
            'Analysis' => [
                'Poor' => 'No reasoning or evidence.',
                'Fair' => 'Some reasoning, limited evidence.',
                'Excellent' => 'Strong reasoning with clear evidence and insight.',
            ],
        ];

        $criteriaByName = $rubric->criteria->keyBy('name');
        $levelsByLabel = $rubric->levels->keyBy('label');

        foreach ($cells as $criteriaName => $row) {
            $criterion = $criteriaByName->get($criteriaName);
            if (! $criterion) continue;

            foreach ($row as $levelLabel => $desc) {
                $level = $levelsByLabel->get($levelLabel);
                if (! $level) continue;

                RubricDescription::query()->updateOrCreate(
                    ['criteria_id' => $criterion->id, 'level_id' => $level->id],
                    ['description' => $desc]
                );
            }
        }
    }
}

