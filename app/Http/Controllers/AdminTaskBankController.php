<?php

namespace App\Http\Controllers;

use App\Models\JobRole;
use App\Models\TaskBank;
use App\Models\TaskQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminTaskBankController extends Controller
{
    private const MENTOR_JOB_REQUIRED_MESSAGE = 'Akun mentor wajib punya jurusan (job) sebelum mengelola task bank.';

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $taskBankQuery = TaskBank::query()
            ->with('jobRole:id,name')
            ->withCount('questions');

        if ($this->isMentorUser()) {
            $taskBankQuery->where('job_role_id', $this->requireMentorJobId());
        }

        $taskBanks = $taskBankQuery
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('assessment_type', 'like', "%{$search}%")
                        ->orWhereHas('jobRole', function ($jq) use ($search) {
                            $jq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $jobsQuery = JobRole::query()->orderBy('name');
        if ($this->isMentorUser()) {
            $jobsQuery->whereKey($this->requireMentorJobId());
        }

        return Inertia::render('Tasks/Admin/Index', [
            'taskBanks' => $taskBanks,
            'jobs' => $jobsQuery->get(['id', 'name']),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_banks,name'],
            'description' => ['nullable', 'string'],
            'job_role_id' => ['nullable', 'exists:job_roles,id'],
            'assessment_type' => ['required', Rule::in(['essay', 'multiple_choice', 'mixed'])],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($this->isMentorUser()) {
            $validated['job_role_id'] = $this->requireMentorJobId();
        }

        $this->assertMentorCanManageJobRoleId($validated['job_role_id'] ?? null);

        TaskBank::query()->create($validated);

        return back()->with('message', 'TASK_BANK_CREATED');
    }

    public function update(Request $request, TaskBank $taskBank): RedirectResponse
    {
        $this->assertMentorCanAccessTaskBank($taskBank);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_banks,name,' . $taskBank->id],
            'description' => ['nullable', 'string'],
            'job_role_id' => ['nullable', 'exists:job_roles,id'],
            'assessment_type' => ['required', Rule::in(['essay', 'multiple_choice', 'mixed'])],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($this->isMentorUser()) {
            $validated['job_role_id'] = $this->requireMentorJobId();
        }

        $this->assertMentorCanManageJobRoleId($validated['job_role_id'] ?? null);

        $taskBank->update($validated);

        return back()->with('message', 'TASK_BANK_UPDATED');
    }

    public function destroy(TaskBank $taskBank): RedirectResponse
    {
        $this->assertMentorCanAccessTaskBank($taskBank);
        $taskBank->delete();

        return back()->with('message', 'TASK_BANK_DELETED');
    }

    public function show(TaskBank $taskBank, Request $request): Response
    {
        $this->assertMentorCanAccessTaskBank($taskBank);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $questions = $taskBank->questions()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('question_text', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Tasks/Admin/Show', [
            'taskBank' => $taskBank->load('jobRole:id,name'),
            'questions' => $questions,
            'importResult' => $request->session()->get('task_bank_import_result'),
            'importTemplate' => [
                'download_url' => asset('examples/task-bank-import-template.json'),
                'fields' => [
                    ['name' => 'pertanyaan', 'required' => true, 'description' => 'Teks soal / pertanyaan utama.'],
                    ['name' => 'tipe_soal', 'required' => false, 'description' => 'Isi `multiple_choice` atau `essay`. Jika kosong akan mengikuti tipe task bank.'],
                    ['name' => 'opsi', 'required' => false, 'description' => 'Wajib untuk soal pilihan ganda. Bisa object berkey `A/B/C/D` atau array string.'],
                    ['name' => 'jawaban', 'required' => false, 'description' => 'Untuk pilihan ganda isi huruf opsi benar seperti `A`, atau isi teks opsi yang benar.'],
                    ['name' => 'bobot', 'required' => false, 'description' => 'Bobot nilai, default `1`.'],
                    ['name' => 'urutan', 'required' => false, 'description' => 'Nomor urut soal, default mengikuti urutan data JSON.'],
                    ['name' => 'is_active', 'required' => false, 'description' => 'Status aktif soal, default `true`.'],
                    ['name' => 'kategori', 'required' => false, 'description' => 'Opsional untuk membantu penyusunan file JSON. Saat ini tidak disimpan karena schema bank soal belum punya kolom kategori.'],
                ],
                'sample' => $this->taskBankImportJsonTemplate(),
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function storeQuestion(Request $request, TaskBank $taskBank): RedirectResponse
    {
        $this->assertMentorCanAccessTaskBank($taskBank);

        $validated = $request->validate([
            'question_text' => ['required', 'string'],
            'question_type' => ['required', Rule::in(['essay', 'multiple_choice'])],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'answer_key' => ['nullable', 'string', 'max:255'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $payload = $this->normalizeQuestionPayload($validated);

        $taskBank->questions()->create($payload);

        return back()->with('message', 'TASK_CREATED');
    }

    public function importQuestionsJson(Request $request, TaskBank $taskBank): RedirectResponse
    {
        $this->assertMentorCanAccessTaskBank($taskBank);

        $validated = $request->validate([
            'import_file' => ['nullable', 'file', 'max:2048', 'mimes:json,txt', 'required_without:import_json_text'],
            'import_json_text' => ['nullable', 'string', 'required_without:import_file'],
            'skip_invalid' => ['nullable', 'boolean'],
        ]);

        $skipInvalid = (bool) ($validated['skip_invalid'] ?? false);
        $importJsonText = trim((string) ($validated['import_json_text'] ?? ''));
        $sourceErrorKey = 'import_file';
        $sourceLabel = 'File JSON';

        if ($importJsonText !== '') {
            $sourceErrorKey = 'import_json_text';
            $sourceLabel = 'Input JSON';
            $rawContents = $importJsonText;
        } else {
            $rawContents = (string) file_get_contents($validated['import_file']->getRealPath());
        }

        try {
            $decoded = json_decode($rawContents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages([
                $sourceErrorKey => "{$sourceLabel} tidak valid: " . $exception->getMessage(),
            ]);
        }

        try {
            $rows = $this->extractImportRows($decoded);
        } catch (ValidationException $exception) {
            throw $this->remapImportValidationErrors($exception, $sourceErrorKey);
        }

        if (count($rows) === 0) {
            throw ValidationException::withMessages([
                $sourceErrorKey => "{$sourceLabel} tidak berisi soal untuk diimport.",
            ]);
        }

        $existingFingerprints = TaskQuestion::query()
            ->where('task_bank_id', $taskBank->id)
            ->pluck('question_text')
            ->map(fn ($text) => $this->questionFingerprint((string) $text))
            ->filter()
            ->values()
            ->all();

        $seenFingerprints = array_fill_keys($existingFingerprints, true);
        $validRows = [];
        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            $humanIndex = $rowIndex + 1;

            if (! is_array($row)) {
                $errors[] = "Soal #{$humanIndex}: format item harus berupa object JSON.";
                continue;
            }

            try {
                $validRows[] = $this->normalizeImportedQuestionRow($row, $humanIndex, $taskBank, $seenFingerprints);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    foreach ($messages as $message) {
                        $errors[] = $message;
                    }
                }
            }
        }

        $successCount = 0;
        if (! empty($errors) && ! $skipInvalid) {
            return back()
                ->withErrors([$sourceErrorKey => 'Import dibatalkan karena ada data yang tidak valid.'])
                ->with('task_bank_import_result', [
                    'success_count' => 0,
                    'failed_count' => count($errors),
                    'skipped_invalid' => false,
                    'errors' => $errors,
                ]);
        }

        if (! empty($validRows)) {
            $now = Carbon::now();
            $payloads = array_map(function (array $row) use ($taskBank, $now) {
                return [
                    'uuid' => (string) Str::uuid(),
                    'task_bank_id' => (int) $taskBank->id,
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'],
                    'options_json' => $row['options_json'] !== null ? json_encode($row['options_json'], JSON_UNESCAPED_UNICODE) : null,
                    'answer_key' => $row['answer_key'],
                    'weight' => (int) $row['weight'],
                    'sort_order' => (int) $row['sort_order'],
                    'is_active' => (bool) $row['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $validRows);

            foreach (array_chunk($payloads, 200) as $chunk) {
                TaskQuestion::query()->insert($chunk);
            }

            $successCount = count($payloads);
        }

        $failedCount = count($errors);
        $message = $successCount > 0
            ? ($failedCount > 0 ? 'TASK_IMPORT_PARTIAL_SUCCESS' : 'TASK_IMPORT_SUCCESS')
            : 'TASK_IMPORT_NO_VALID_DATA';

        return back()
            ->with('message', $message)
            ->with('task_bank_import_result', [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'skipped_invalid' => $skipInvalid,
                'errors' => $errors,
            ]);
    }

    public function updateQuestion(Request $request, TaskBank $taskBank, TaskQuestion $question): RedirectResponse
    {
        $this->assertMentorCanAccessTaskBank($taskBank);

        if ((int) $question->task_bank_id !== (int) $taskBank->id) {
            abort(404);
        }

        $validated = $request->validate([
            'question_text' => ['required', 'string'],
            'question_type' => ['required', Rule::in(['essay', 'multiple_choice'])],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'answer_key' => ['nullable', 'string', 'max:255'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $payload = $this->normalizeQuestionPayload($validated);

        $question->update($payload);

        return back()->with('message', 'TASK_UPDATED');
    }

    public function destroyQuestion(TaskBank $taskBank, TaskQuestion $question): RedirectResponse
    {
        $this->assertMentorCanAccessTaskBank($taskBank);

        if ((int) $question->task_bank_id !== (int) $taskBank->id) {
            abort(404);
        }

        $question->delete();

        return back()->with('message', 'TASK_DELETED');
    }

    private function normalizeQuestionPayload(array $validated): array
    {
        $questionType = $validated['question_type'];

        $options = array_values(array_filter(
            $validated['options'] ?? [],
            fn ($value) => trim((string) $value) !== ''
        ));

        if ($questionType === 'multiple_choice') {
            if (count($options) < 2) {
                throw ValidationException::withMessages([
                    'options' => 'Pilihan ganda harus memiliki minimal 2 opsi.',
                ]);
            }

            if (! in_array($validated['answer_key'], $options, true)) {
                throw ValidationException::withMessages([
                    'answer_key' => 'Jawaban benar harus salah satu dari opsi.',
                ]);
            }
        } else {
            $options = [];
            $validated['answer_key'] = null;
        }

        return [
            'question_text' => $validated['question_text'],
            'question_type' => $questionType,
            'options_json' => ! empty($options) ? $options : null,
            'answer_key' => $validated['answer_key'] ?? null,
            'weight' => (int) $validated['weight'],
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    private function extractImportRows(mixed $decoded): array
    {
        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions']) && array_is_list($decoded['questions'])) {
            return $decoded['questions'];
        }

        throw ValidationException::withMessages([
            'import_file' => 'Struktur JSON harus berupa array soal atau object dengan key `questions`.',
        ]);
    }

    private function normalizeImportedQuestionRow(array $row, int $humanIndex, TaskBank $taskBank, array &$seenFingerprints): array
    {
        $questionText = trim((string) ($row['pertanyaan'] ?? $row['question_text'] ?? $row['question'] ?? ''));
        if ($questionText === '') {
            throw ValidationException::withMessages([
                "import_file" => "Soal #{$humanIndex}: field `pertanyaan` wajib diisi.",
            ]);
        }

        $questionType = $this->resolveImportedQuestionType($row, $taskBank);
        $taskBankType = (string) ($taskBank->assessment_type ?? 'essay');

        if ($taskBankType === 'essay' && $questionType !== 'essay') {
            throw ValidationException::withMessages([
                'import_file' => "Soal #{$humanIndex}: task bank ini hanya menerima soal essay.",
            ]);
        }

        if ($taskBankType === 'multiple_choice' && $questionType !== 'multiple_choice') {
            throw ValidationException::withMessages([
                'import_file' => "Soal #{$humanIndex}: task bank ini hanya menerima soal multiple choice.",
            ]);
        }

        $fingerprint = $this->questionFingerprint($questionText);
        if (isset($seenFingerprints[$fingerprint])) {
            throw ValidationException::withMessages([
                'import_file' => "Soal #{$humanIndex}: pertanyaan duplikat terdeteksi untuk `{$questionText}`.",
            ]);
        }

        $weight = $this->normalizePositiveInt($row['bobot'] ?? $row['weight'] ?? 1, 1, 100, "Soal #{$humanIndex}: field `bobot` harus angka 1-100.");
        $sortOrder = $this->normalizePositiveInt($row['urutan'] ?? $row['sort_order'] ?? $humanIndex, 1, 1000000, "Soal #{$humanIndex}: field `urutan` harus angka minimal 1.");
        $isActive = $this->normalizeImportBoolean($row['is_active'] ?? true, "Soal #{$humanIndex}: field `is_active` harus boolean true/false.");

        $options = null;
        $answerKey = null;

        if ($questionType === 'multiple_choice') {
            [$options, $answerKey] = $this->normalizeImportedMultipleChoiceData($row, $humanIndex);
        }

        $seenFingerprints[$fingerprint] = true;

        return [
            'question_text' => $questionText,
            'question_type' => $questionType,
            'options_json' => $options,
            'answer_key' => $answerKey,
            'weight' => $weight,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];
    }

    private function resolveImportedQuestionType(array $row, TaskBank $taskBank): string
    {
        $rawType = trim((string) ($row['tipe_soal'] ?? $row['question_type'] ?? ''));
        if ($rawType !== '') {
            $normalized = str_replace(['-', ' '], '_', strtolower($rawType));
            if (in_array($normalized, ['essay', 'multiple_choice'], true)) {
                return $normalized;
            }
        }

        $taskBankType = (string) ($taskBank->assessment_type ?? 'essay');
        if (in_array($taskBankType, ['essay', 'multiple_choice'], true)) {
            return $taskBankType;
        }

        $hasOptions = array_key_exists('opsi', $row) || array_key_exists('options', $row);
        return $hasOptions ? 'multiple_choice' : 'essay';
    }

    private function normalizeImportedMultipleChoiceData(array $row, int $humanIndex): array
    {
        $rawOptions = $row['opsi'] ?? $row['options'] ?? null;
        if (! is_array($rawOptions) || count($rawOptions) < 2) {
            throw ValidationException::withMessages([
                'import_file' => "Soal #{$humanIndex}: field `opsi` wajib berupa object/array dengan minimal 2 opsi.",
            ]);
        }

        $optionMap = [];
        if (array_is_list($rawOptions)) {
            foreach ($rawOptions as $index => $value) {
                $label = chr(65 + $index);
                $text = trim((string) $value);
                if ($text === '') {
                    throw ValidationException::withMessages([
                        'import_file' => "Soal #{$humanIndex}: semua nilai opsi harus terisi.",
                    ]);
                }

                $optionMap[$label] = $text;
            }
        } else {
            foreach ($rawOptions as $label => $value) {
                $normalizedLabel = strtoupper(trim((string) $label));
                $text = trim((string) $value);

                if ($normalizedLabel === '' || $text === '') {
                    throw ValidationException::withMessages([
                        'import_file' => "Soal #{$humanIndex}: key dan nilai opsi tidak boleh kosong.",
                    ]);
                }

                $optionMap[$normalizedLabel] = $text;
            }
        }

        $answerInput = trim((string) ($row['jawaban'] ?? $row['answer'] ?? $row['answer_key'] ?? ''));
        if ($answerInput === '') {
            throw ValidationException::withMessages([
                'import_file' => "Soal #{$humanIndex}: field `jawaban` wajib diisi untuk soal multiple choice.",
            ]);
        }

        $normalizedAnswerLabel = strtoupper($answerInput);
        if (isset($optionMap[$normalizedAnswerLabel])) {
            return [array_values($optionMap), $optionMap[$normalizedAnswerLabel]];
        }

        $matchedOption = collect($optionMap)->first(fn ($text) => trim((string) $text) === $answerInput);
        if ($matchedOption !== null) {
            return [array_values($optionMap), $matchedOption];
        }

        throw ValidationException::withMessages([
            'import_file' => "Soal #{$humanIndex}: field `jawaban` harus sesuai label opsi (mis. A/B/C/D) atau isi opsi yang valid.",
        ]);
    }

    private function normalizePositiveInt(mixed $value, int $min, int $max, string $errorMessage): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['import_file' => $errorMessage]);
        }

        $intValue = (int) $value;
        if ($intValue < $min || $intValue > $max) {
            throw ValidationException::withMessages(['import_file' => $errorMessage]);
        }

        return $intValue;
    }

    private function normalizeImportBoolean(mixed $value, string $errorMessage): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            throw ValidationException::withMessages(['import_file' => $errorMessage]);
        }

        return $normalized;
    }

    private function remapImportValidationErrors(ValidationException $exception, string $targetErrorKey): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $errorMessages) {
            foreach ((array) $errorMessages as $message) {
                $normalized = trim((string) $message);
                if ($normalized !== '') {
                    $messages[] = $normalized;
                }
            }
        }

        if ($messages === []) {
            $messages[] = 'Data import tidak valid.';
        }

        return ValidationException::withMessages([
            $targetErrorKey => $messages,
        ]);
    }

    private function questionFingerprint(string $text): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($text)));
    }

    private function taskBankImportJsonTemplate(): array
    {
        return [
            [
                'pertanyaan' => 'Ibukota Indonesia adalah?',
                'tipe_soal' => 'multiple_choice',
                'opsi' => [
                    'A' => 'Jakarta',
                    'B' => 'Bandung',
                    'C' => 'Surabaya',
                    'D' => 'Medan',
                ],
                'jawaban' => 'A',
                'kategori' => 'Geografi',
                'bobot' => 1,
                'urutan' => 1,
                'is_active' => true,
            ],
            [
                'pertanyaan' => 'Planet terbesar di tata surya adalah?',
                'tipe_soal' => 'multiple_choice',
                'opsi' => [
                    'A' => 'Mars',
                    'B' => 'Jupiter',
                    'C' => 'Venus',
                    'D' => 'Saturnus',
                ],
                'jawaban' => 'B',
                'kategori' => 'Sains',
                'bobot' => 1,
                'urutan' => 2,
                'is_active' => true,
            ],
            [
                'pertanyaan' => 'Jelaskan perbedaan utama antara CPU dan GPU.',
                'tipe_soal' => 'essay',
                'kategori' => 'Komputer',
                'bobot' => 2,
                'urutan' => 3,
                'is_active' => true,
            ],
        ];
    }

    private function isMentorUser(): bool
    {
        return (bool) auth()->user()?->isMentor();
    }

    private function requireMentorJobId(): int
    {
        $jobId = (int) (auth()->user()?->job_id ?? 0);
        abort_if($jobId <= 0, 403, self::MENTOR_JOB_REQUIRED_MESSAGE);
        return $jobId;
    }

    private function assertMentorCanAccessTaskBank(TaskBank $taskBank): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        abort_unless((int) ($taskBank->job_role_id ?? 0) === $mentorJobId, 403, 'MENTOR_CANNOT_ACCESS_TASK_BANK_OUTSIDE_JOB');
    }

    private function assertMentorCanManageJobRoleId(?int $jobRoleId): void
    {
        if (! $this->isMentorUser()) {
            return;
        }

        $mentorJobId = $this->requireMentorJobId();
        abort_unless((int) $jobRoleId === $mentorJobId, 403, 'MENTOR_CANNOT_ASSIGN_TASK_BANK_OUTSIDE_JOB');
    }

}
