<?php

namespace Database\Seeders;

use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use App\Models\TaskBank;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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

        $autoEssayRubric = Rubric::query()->firstOrCreate(
            ['title' => 'Essay Auto Grade Rubric (System)', 'mentor_id' => $mentor->id],
            ['description' => 'Rubric khusus auto-grading essay agar penilaian AI lebih konsisten.', 'max_score' => 100]
        );

        $autoLevels = [
            ['level' => 1, 'label' => 'Very Poor', 'score_value' => 1],
            ['level' => 2, 'label' => 'Poor', 'score_value' => 2],
            ['level' => 3, 'label' => 'Fair', 'score_value' => 3],
            ['level' => 4, 'label' => 'Good', 'score_value' => 4],
            ['level' => 5, 'label' => 'Excellent', 'score_value' => 5],
        ];

        foreach ($autoLevels as $level) {
            RubricLevel::query()->updateOrCreate(
                ['rubric_id' => $autoEssayRubric->id, 'level' => $level['level']],
                ['label' => $level['label'], 'score_value' => $level['score_value']]
            );
        }

        $autoCriteria = [
            ['name' => 'Akurasi Konsep', 'weight' => 30, 'order' => 1],
            ['name' => 'Kelengkapan Jawaban', 'weight' => 30, 'order' => 2],
            ['name' => 'Alur Penjelasan', 'weight' => 20, 'order' => 3],
            ['name' => 'Konteks Teknis', 'weight' => 20, 'order' => 4],
        ];

        foreach ($autoCriteria as $criterion) {
            RubricCriterion::query()->updateOrCreate(
                ['rubric_id' => $autoEssayRubric->id, 'order' => $criterion['order']],
                ['name' => $criterion['name'], 'weight' => $criterion['weight']]
            );
        }

        $autoEssayRubric->load(['criteria', 'levels']);
        $autoCriteriaByName = $autoEssayRubric->criteria->keyBy('name');
        $autoLevelsByLabel = $autoEssayRubric->levels->keyBy('label');

        $autoCells = [
            'Akurasi Konsep' => [
                'Very Poor' => 'Konsep inti tidak tepat atau tidak relevan dengan pertanyaan.',
                'Poor' => 'Ada konsep yang benar, namun dominan keliru.',
                'Fair' => 'Konsep utama mulai benar tetapi masih terdapat kekurangan penting.',
                'Good' => 'Konsep utama benar dan cukup konsisten.',
                'Excellent' => 'Konsep sangat akurat, tepat, dan konsisten.',
            ],
            'Kelengkapan Jawaban' => [
                'Very Poor' => 'Jawaban sangat minim dan tidak mencakup poin utama.',
                'Poor' => 'Hanya mencakup sebagian kecil poin penting.',
                'Fair' => 'Poin penting tercakup sebagian, detail belum memadai.',
                'Good' => 'Sebagian besar poin penting tercakup dengan baik.',
                'Excellent' => 'Semua poin penting tercakup lengkap dan jelas.',
            ],
            'Alur Penjelasan' => [
                'Very Poor' => 'Penjelasan tidak runtut dan sulit dipahami.',
                'Poor' => 'Alur kurang rapi dan sering melompat.',
                'Fair' => 'Alur cukup terlihat namun belum stabil.',
                'Good' => 'Alur runtut dan mudah diikuti.',
                'Excellent' => 'Alur sangat terstruktur, ringkas, dan koheren.',
            ],
            'Konteks Teknis' => [
                'Very Poor' => 'Tidak ada konteks teknis yang relevan.',
                'Poor' => 'Konteks teknis sangat terbatas.',
                'Fair' => 'Konteks teknis ada namun kurang tepat/detail.',
                'Good' => 'Konteks teknis tepat dan cukup mendukung jawaban.',
                'Excellent' => 'Konteks teknis kuat, tepat, dan relevan penuh.',
            ],
        ];

        foreach ($autoCells as $criteriaName => $row) {
            $criterion = $autoCriteriaByName->get($criteriaName);
            if (! $criterion) {
                continue;
            }

            foreach ($row as $levelLabel => $description) {
                $level = $autoLevelsByLabel->get($levelLabel);
                if (! $level) {
                    continue;
                }

                RubricDescription::query()->updateOrCreate(
                    ['criteria_id' => $criterion->id, 'level_id' => $level->id],
                    ['description' => $description]
                );
            }
        }

        if (Schema::hasColumn('task_banks', 'rubric_id')) {
            TaskBank::query()
                ->whereIn('assessment_type', ['essay', 'mixed'])
                ->whereNull('rubric_id')
                ->update(['rubric_id' => $autoEssayRubric->id]);
        }
    }
}
