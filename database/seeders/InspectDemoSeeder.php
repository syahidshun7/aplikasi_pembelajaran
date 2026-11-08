<?php

namespace Database\Seeders;

use App\Models\Quest;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\TaskQuestion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InspectDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('InspectDemoSeeder hanya boleh dijalankan pada environment local.');
        }

        DB::transaction(function () {
            $student = User::query()->firstOrCreate(
                ['email' => 'inspect-demo@local.test'],
                [
                    'name' => 'Peserta Demo Inspect',
                    'username' => 'inspect_demo',
                    'password' => Hash::make(Str::random(48)),
                    'role' => User::ROLE_USER,
                ],
            );

            $this->mixed($student);
            $this->platforming($student);
            $this->wordMatch($student);
        });
    }

    private function bank(string $title, string $type): TaskBank
    {
        return TaskBank::query()->firstOrCreate(
            ['name' => $title],
            ['description' => 'Bank soal contoh untuk melihat tampilan inspect redesign.', 'assessment_type' => $type, 'is_active' => true],
        );
    }

    private function question(TaskBank $bank, string $title, string $type, ?array $options, ?string $key, int $weight, int $order): TaskQuestion
    {
        return TaskQuestion::query()->firstOrCreate(
            ['task_bank_id' => $bank->id, 'question_text' => $title],
            [
                'question_type' => $type,
                'options_json' => $options,
                'answer_key' => $key,
                'weight' => $weight,
                'sort_order' => $order,
                'is_active' => true,
            ],
        );
    }

    private function quest(string $title, TaskBank $bank, string $description): Quest
    {
        return Quest::query()->firstOrCreate(
            ['title' => $title],
            [
                'description' => $description,
                'difficulty' => 'C-Rank',
                'status' => Quest::STATUS_AVAILABLE,
                'quest_type' => Quest::TYPE_OPTIONAL,
                'task_bank_id' => $bank->id,
                'reward_gold' => 0,
                'reward_exp' => 0,
            ],
        );
    }

    private function submission(User $student, Quest $quest, array $answers, string $type, int $grade = 0, int $correct = 0, int $total = 0): Submission
    {
        $automatic = in_array($type, ['platforming', 'word_match'], true);

        return Submission::query()->firstOrCreate(
            ['user_id' => $student->id, 'quest_id' => $quest->id],
            [
                'content' => 'Submission contoh untuk memeriksa tampilan inspect.',
                'status' => $automatic ? Submission::STATUS_APPROVED : Submission::STATUS_PENDING,
                'pipeline_status' => $automatic ? Submission::PIPELINE_STATUS_EVALUATED : Submission::PIPELINE_STATUS_PENDING_PREPROCESSING,
                'attempt_number' => 1,
                'reward_eligible' => false,
                'grade' => $grade,
                'scores_detail' => [
                    'source' => $automatic ? 'task_bank_auto_check' : 'raw_task_bank_submission',
                    'assessment_type' => $type,
                    'answers' => $answers,
                    'correct_questions' => $correct,
                    'total_questions' => $total,
                ],
            ],
        );
    }

    private function mixed(User $student): void
    {
        $bank = $this->bank('[DEMO INSPECT] Bank Mixed', 'mixed');
        $choice = $this->question($bank, 'Framework PHP mana yang dipakai proyek ini?', 'multiple_choice', ['Laravel', 'Django', 'Express'], 'Laravel', 2, 1);
        $essay = $this->question($bank, 'Jelaskan fungsi route dan controller pada Laravel.', 'essay', null, null, 3, 2);
        $quest = $this->quest('[DEMO INSPECT] Mixed — Laravel dasar', $bank, 'Contoh soal pilihan ganda dan esai untuk inspeksi penilaian campuran.');
        $this->submission($student, $quest, [
            $choice->uuid => 'Laravel',
            $essay->uuid => 'Route menerima URL dan mengarahkannya ke controller. Controller memproses request, mengambil data, lalu mengembalikan respons.',
        ], 'mixed');
    }

    private function platforming(User $student): void
    {
        $bank = $this->bank('[DEMO INSPECT] Bank Platforming', 'platforming');
        $first = $this->question($bank, 'Stage 1 — HTTP', 'platforming', ['stages' => [[
            'prompt' => 'Apa kepanjangan HTTP?', 'correct_answer' => 'HyperText Transfer Protocol',
            'wrong_answers' => ['High Transfer Text Protocol', 'Hyper Tool Transfer Process'],
        ]]], null, 1, 1);
        $second = $this->question($bank, 'Stage 2 — Laravel', 'platforming', ['stages' => [[
            'prompt' => 'File route web Laravel ada di mana?', 'correct_answer' => 'routes/web.php',
            'wrong_answers' => ['app/Models/User.php', 'public/index.php'],
        ]]], null, 1, 2);
        $quest = $this->quest('[DEMO INSPECT] Platforming — Web basics', $bank, 'Contoh dua stage game platforming dengan satu jawaban benar dan satu salah.');
        $payload = json_encode(['answers' => [
            ['stage' => 0, 'answer' => 'HyperText Transfer Protocol', 'correct' => true],
            ['stage' => 1, 'answer' => 'public/index.php', 'correct' => false],
        ], 'level' => 1, 'score' => 1, 'total' => 2], JSON_THROW_ON_ERROR);
        $this->submission($student, $quest, [$first->uuid => $payload, $second->uuid => $payload], 'platforming', 50, 1, 2);
    }

    private function wordMatch(User $student): void
    {
        $bank = $this->bank('[DEMO INSPECT] Bank Word Match', 'word_match');
        $question = $this->question($bank, 'Lengkapi kalimat tentang Laravel.', 'word_match', [
            'sentence' => 'Laravel menggunakan pola ___ untuk memisahkan model, tampilan, dan pengendali.',
            'blanks' => ['MVC'],
            'distractors' => ['MVP', 'MVVM'],
        ], null, 1, 1);
        $quest = $this->quest('[DEMO INSPECT] Word Match — Pola MVC', $bank, 'Contoh permainan mencocokkan kata pada kalimat.');
        $payload = json_encode(['placed' => ['MVC'], 'correct_count' => 1, 'total' => 1, 'timeout' => false, 'complete' => true], JSON_THROW_ON_ERROR);
        $this->submission($student, $quest, [$question->uuid => $payload], 'word_match', 100, 1, 1);
    }
}
