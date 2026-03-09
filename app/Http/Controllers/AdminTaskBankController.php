<?php

namespace App\Http\Controllers;

use App\Models\JobRole;
use App\Models\TaskBank;
use App\Models\TaskQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
