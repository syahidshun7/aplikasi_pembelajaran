<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rubrics\StoreRubricRequest;
use App\Http\Requests\Rubrics\UpdateRubricRequest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use App\Services\RubricScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RubricController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rubric::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $user = $request->user();

        $query = Rubric::query()
            ->with('mentor:id,name')
            ->when($user?->isMentor(), fn ($q) => $q->where('mentor_id', $user->id))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');

        return Inertia::render('Rubric/Index', [
            'rubrics' => $query->paginate(10)->withQueryString(),
            'filters' => [
                'search' => $search,
            ],
            'importResult' => $request->session()->get('rubric_import_result'),
            'importTemplate' => [
                'download_url' => asset('examples/rubric-import-template.json'),
                'fields' => [
                    ['name' => 'rubric.title', 'required' => true, 'description' => 'Judul rubric.'],
                    ['name' => 'rubric.description', 'required' => false, 'description' => 'Deskripsi rubric.'],
                    ['name' => 'criteria[]', 'required' => true, 'description' => 'Array criteria, tiap item minimal punya `name` dan `weight`.'],
                    ['name' => 'levels[]', 'required' => true, 'description' => 'Array levels, minimal 2 level. Tiap item wajib punya `level`, `label`, `score_value`.'],
                    ['name' => 'matrix[]', 'required' => false, 'description' => 'Deskripsi sel matrix. Bisa pakai format export (`criteria_id`,`level_id`,`description`) atau key berbasis nama/key.'],
                ],
                'sample' => $this->rubricImportJsonTemplate(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Rubric::class);

        return Inertia::render('Rubric/Create');
    }

    public function store(StoreRubricRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $rubric = DB::transaction(function () use ($validated, $user) {
            $rubric = Rubric::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'mentor_id' => $user->id,
                'max_score' => 0,
            ]);

            // Default levels so the matrix editor is usable immediately.
            $defaults = [
                ['level' => 1, 'label' => 'Poor', 'score_value' => 1],
                ['level' => 2, 'label' => 'Fair', 'score_value' => 2],
                ['level' => 3, 'label' => 'Good', 'score_value' => 3],
                ['level' => 4, 'label' => 'Excellent', 'score_value' => 4],
            ];

            foreach ($defaults as $level) {
                RubricLevel::create([
                    'rubric_id' => $rubric->id,
                    'level' => $level['level'],
                    'label' => $level['label'],
                    'score_value' => $level['score_value'],
                ]);
            }

            return $rubric;
        });

        return redirect()->route('admin.rubrics.edit', $rubric)->with('message', 'RUBRIC_CREATED');
    }

    public function show(Rubric $rubric): Response
    {
        $this->authorize('view', $rubric);

        return Inertia::render('Rubric/Show', $this->buildRubricPayload($rubric));
    }

    public function edit(Rubric $rubric): Response
    {
        $this->authorize('update', $rubric);

        return Inertia::render('Rubric/Edit', $this->buildRubricPayload($rubric));
    }

    public function update(UpdateRubricRequest $request, Rubric $rubric): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($rubric, $validated) {
            $rubric->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);

            if (! array_key_exists('descriptions', $validated)) {
                return;
            }

            $rubric->loadMissing(['criteria:id,rubric_id', 'levels:id,rubric_id']);
            $criteriaIds = $rubric->criteria->pluck('id')->map(fn ($v) => (int) $v)->all();
            $levelIds = $rubric->levels->pluck('id')->map(fn ($v) => (int) $v)->all();
            $criteriaLookup = array_fill_keys($criteriaIds, true);
            $levelLookup = array_fill_keys($levelIds, true);

            foreach ($validated['descriptions'] as $cell) {
                $criteriaId = (int) $cell['criteria_id'];
                $levelId = (int) $cell['level_id'];

                if (! isset($criteriaLookup[$criteriaId]) || ! isset($levelLookup[$levelId])) {
                    abort(422, 'INVALID_RUBRIC_MATRIX_CELL');
                }

                $desc = trim((string) ($cell['description'] ?? ''));

                if ($desc === '') {
                    RubricDescription::query()
                        ->where('criteria_id', $criteriaId)
                        ->where('level_id', $levelId)
                        ->delete();
                    continue;
                }

                RubricDescription::updateOrCreate(
                    ['criteria_id' => $criteriaId, 'level_id' => $levelId],
                    ['description' => $desc]
                );
            }
        });

        return back()->with('message', 'RUBRIC_UPDATED');
    }

    public function destroy(Rubric $rubric): RedirectResponse
    {
        $this->authorize('delete', $rubric);

        $rubric->delete();

        return redirect()->route('admin.rubrics.index')->with('message', 'RUBRIC_DELETED');
    }

    public function export(Rubric $rubric): JsonResponse
    {
        $this->authorize('view', $rubric);

        return response()->json($rubric->exportAsJson());
    }

    public function importJson(Request $request, RubricScoringService $scoring): RedirectResponse
    {
        $this->authorize('create', Rubric::class);

        $validated = $request->validate([
            'import_file' => ['nullable', 'file', 'max:4096', 'mimes:json,txt', 'required_without:import_json_text'],
            'import_json_text' => ['nullable', 'string', 'required_without:import_file'],
        ]);

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
            $payload = $this->normalizeImportedRubricPayload($decoded);
        } catch (ValidationException $exception) {
            throw $this->remapImportValidationErrors($exception, $sourceErrorKey);
        }

        $rubric = DB::transaction(function () use ($payload, $request, $scoring) {
            $rubric = Rubric::query()->create([
                'title' => $payload['title'],
                'description' => $payload['description'],
                'mentor_id' => (int) $request->user()->id,
                'max_score' => 0,
            ]);

            $criteriaIdByIndex = [];
            foreach ($payload['criteria'] as $index => $criterionRow) {
                $criterion = RubricCriterion::query()->create([
                    'rubric_id' => $rubric->id,
                    'name' => $criterionRow['name'],
                    'weight' => $criterionRow['weight'],
                    'order' => $criterionRow['order'],
                ]);

                $criteriaIdByIndex[(int) $index] = (int) $criterion->id;
            }

            $levelIdByIndex = [];
            foreach ($payload['levels'] as $index => $levelRow) {
                $level = RubricLevel::query()->create([
                    'rubric_id' => $rubric->id,
                    'level' => $levelRow['level'],
                    'label' => $levelRow['label'],
                    'score_value' => $levelRow['score_value'],
                ]);

                $levelIdByIndex[(int) $index] = (int) $level->id;
            }

            foreach ($payload['matrix'] as $cell) {
                $criteriaId = $criteriaIdByIndex[(int) $cell['criteria_index']] ?? 0;
                $levelId = $levelIdByIndex[(int) $cell['level_index']] ?? 0;
                $description = trim((string) ($cell['description'] ?? ''));

                if ($criteriaId <= 0 || $levelId <= 0 || $description === '') {
                    continue;
                }

                RubricDescription::query()->updateOrCreate(
                    ['criteria_id' => $criteriaId, 'level_id' => $levelId],
                    ['description' => $description]
                );
            }

            $rubric->update([
                'max_score' => $scoring->calculateMaxScore($rubric->fresh()),
            ]);

            return $rubric;
        });

        return redirect()
            ->route('admin.rubrics.index')
            ->with('message', 'RUBRIC_IMPORT_SUCCESS')
            ->with('rubric_import_result', [
                'success' => true,
                'title' => $rubric->title,
                'criteria_count' => count($payload['criteria']),
                'levels_count' => count($payload['levels']),
                'matrix_count' => count($payload['matrix']),
            ]);
    }

    private function normalizeImportedRubricPayload(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'import_file' => 'Struktur JSON rubric harus berupa object.',
            ]);
        }

        $rubricMeta = isset($decoded['rubric']) && is_array($decoded['rubric'])
            ? $decoded['rubric']
            : [];

        $title = trim((string) ($rubricMeta['title'] ?? $decoded['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'import_file' => 'Field `rubric.title` wajib diisi.',
            ]);
        }

        $description = trim((string) ($rubricMeta['description'] ?? $decoded['description'] ?? ''));
        $description = $description !== '' ? $description : null;

        $criteriaRows = $decoded['criteria'] ?? null;
        if (! is_array($criteriaRows) || ! array_is_list($criteriaRows) || count($criteriaRows) === 0) {
            throw ValidationException::withMessages([
                'import_file' => 'Field `criteria` wajib berupa array dan minimal 1 item.',
            ]);
        }

        $levelsRows = $decoded['levels'] ?? null;
        if (! is_array($levelsRows) || ! array_is_list($levelsRows) || count($levelsRows) < 2) {
            throw ValidationException::withMessages([
                'import_file' => 'Field `levels` wajib berupa array dan minimal 2 item.',
            ]);
        }

        $criteria = [];
        $criteriaOldIdMap = [];
        $criteriaKeyMap = [];
        $criteriaNameMap = [];

        foreach ($criteriaRows as $index => $row) {
            $humanIndex = $index + 1;
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'import_file' => "Criteria #{$humanIndex}: format item harus object JSON.",
                ]);
            }

            $name = trim((string) ($row['name'] ?? $row['criteria_name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'import_file' => "Criteria #{$humanIndex}: field `name` wajib diisi.",
                ]);
            }

            $weight = $this->normalizeNumeric($row['weight'] ?? null, "Criteria #{$humanIndex}: field `weight` wajib angka >= 0.");
            if ($weight < 0) {
                throw ValidationException::withMessages([
                    'import_file' => "Criteria #{$humanIndex}: field `weight` wajib angka >= 0.",
                ]);
            }

            $order = $this->normalizePositiveInt($row['order'] ?? ($index + 1), 0, 1000000, "Criteria #{$humanIndex}: field `order` harus angka >= 0.");
            $criteria[] = [
                'name' => $name,
                'weight' => $weight,
                'order' => $order,
            ];

            if (array_key_exists('id', $row) && $row['id'] !== null && $row['id'] !== '') {
                $criteriaOldIdMap[(string) $row['id']] = (int) $index;
            }

            $criteriaKey = trim((string) ($row['key'] ?? ''));
            if ($criteriaKey !== '') {
                $criteriaKeyMap[mb_strtolower($criteriaKey)] = (int) $index;
            }

            $criteriaNameMap[mb_strtolower($name)] = (int) $index;
        }

        $levels = [];
        $levelOldIdMap = [];
        $levelKeyMap = [];
        $levelNumberMap = [];
        $levelLabelMap = [];
        $seenLevelNumbers = [];

        foreach ($levelsRows as $index => $row) {
            $humanIndex = $index + 1;
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'import_file' => "Level #{$humanIndex}: format item harus object JSON.",
                ]);
            }

            $levelNumber = $this->normalizePositiveInt($row['level'] ?? null, 1, 1000000, "Level #{$humanIndex}: field `level` harus angka minimal 1.");
            if (isset($seenLevelNumbers[$levelNumber])) {
                throw ValidationException::withMessages([
                    'import_file' => "Level #{$humanIndex}: nilai `level` duplikat ({$levelNumber}).",
                ]);
            }
            $seenLevelNumbers[$levelNumber] = true;

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                throw ValidationException::withMessages([
                    'import_file' => "Level #{$humanIndex}: field `label` wajib diisi.",
                ]);
            }

            $scoreValue = $this->normalizeNumeric($row['score_value'] ?? null, "Level #{$humanIndex}: field `score_value` wajib angka >= 0.");
            if ($scoreValue < 0) {
                throw ValidationException::withMessages([
                    'import_file' => "Level #{$humanIndex}: field `score_value` wajib angka >= 0.",
                ]);
            }

            $levels[] = [
                'level' => $levelNumber,
                'label' => $label,
                'score_value' => $scoreValue,
            ];

            if (array_key_exists('id', $row) && $row['id'] !== null && $row['id'] !== '') {
                $levelOldIdMap[(string) $row['id']] = (int) $index;
            }

            $levelKey = trim((string) ($row['key'] ?? ''));
            if ($levelKey !== '') {
                $levelKeyMap[mb_strtolower($levelKey)] = (int) $index;
            }

            $levelNumberMap[(string) $levelNumber] = (int) $index;
            $levelLabelMap[mb_strtolower($label)] = (int) $index;
        }

        $matrixRows = $decoded['matrix'] ?? [];
        if (! is_array($matrixRows) || ! array_is_list($matrixRows)) {
            throw ValidationException::withMessages([
                'import_file' => 'Field `matrix` harus array jika diisi.',
            ]);
        }

        $matrix = [];
        $seenCells = [];
        foreach ($matrixRows as $index => $row) {
            $humanIndex = $index + 1;
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'import_file' => "Matrix #{$humanIndex}: format item harus object JSON.",
                ]);
            }

            $criteriaIndex = $this->resolveCriteriaIndex($row, $criteriaOldIdMap, $criteriaKeyMap, $criteriaNameMap);
            $levelIndex = $this->resolveLevelIndex($row, $levelOldIdMap, $levelKeyMap, $levelNumberMap, $levelLabelMap);

            if ($criteriaIndex === null || $levelIndex === null) {
                throw ValidationException::withMessages([
                    'import_file' => "Matrix #{$humanIndex}: referensi criteria/level tidak ditemukan.",
                ]);
            }

            $cellDescription = trim((string) ($row['description'] ?? $row['text'] ?? ''));
            if ($cellDescription === '') {
                continue;
            }

            $cellFingerprint = "{$criteriaIndex}:{$levelIndex}";
            if (isset($seenCells[$cellFingerprint])) {
                throw ValidationException::withMessages([
                    'import_file' => "Matrix #{$humanIndex}: kombinasi criteria-level duplikat.",
                ]);
            }
            $seenCells[$cellFingerprint] = true;

            $matrix[] = [
                'criteria_index' => $criteriaIndex,
                'level_index' => $levelIndex,
                'description' => $cellDescription,
            ];
        }

        return [
            'title' => $title,
            'description' => $description,
            'criteria' => $criteria,
            'levels' => $levels,
            'matrix' => $matrix,
        ];
    }

    private function resolveCriteriaIndex(array $row, array $oldIdMap, array $keyMap, array $nameMap): ?int
    {
        $rawId = $row['criteria_id'] ?? null;
        if ($rawId !== null && isset($oldIdMap[(string) $rawId])) {
            return (int) $oldIdMap[(string) $rawId];
        }

        $rawKey = trim((string) ($row['criteria_key'] ?? ''));
        if ($rawKey !== '' && isset($keyMap[mb_strtolower($rawKey)])) {
            return (int) $keyMap[mb_strtolower($rawKey)];
        }

        $rawName = trim((string) ($row['criteria_name'] ?? ''));
        if ($rawName !== '' && isset($nameMap[mb_strtolower($rawName)])) {
            return (int) $nameMap[mb_strtolower($rawName)];
        }

        return null;
    }

    private function resolveLevelIndex(array $row, array $oldIdMap, array $keyMap, array $numberMap, array $labelMap): ?int
    {
        $rawId = $row['level_id'] ?? null;
        if ($rawId !== null && isset($oldIdMap[(string) $rawId])) {
            return (int) $oldIdMap[(string) $rawId];
        }

        $rawKey = trim((string) ($row['level_key'] ?? ''));
        if ($rawKey !== '' && isset($keyMap[mb_strtolower($rawKey)])) {
            return (int) $keyMap[mb_strtolower($rawKey)];
        }

        $rawLevel = $row['level'] ?? null;
        if ($rawLevel !== null && isset($numberMap[(string) $rawLevel])) {
            return (int) $numberMap[(string) $rawLevel];
        }

        $rawLabel = trim((string) ($row['level_label'] ?? ''));
        if ($rawLabel !== '' && isset($labelMap[mb_strtolower($rawLabel)])) {
            return (int) $labelMap[mb_strtolower($rawLabel)];
        }

        return null;
    }

    private function normalizeNumeric(mixed $value, string $errorMessage): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['import_file' => $errorMessage]);
        }

        return (float) $value;
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
            $messages[] = 'Data import rubric tidak valid.';
        }

        return ValidationException::withMessages([
            $targetErrorKey => $messages,
        ]);
    }

    private function rubricImportJsonTemplate(): array
    {
        return [
            'rubric' => [
                'title' => 'Essay Rubric (Sample)',
                'description' => 'Rubric untuk menilai karya essay secara terstruktur.',
            ],
            'criteria' => [
                ['id' => 'c1', 'name' => 'Kebenaran Konsep', 'weight' => 50, 'order' => 1],
                ['id' => 'c2', 'name' => 'Struktur Penulisan', 'weight' => 30, 'order' => 2],
                ['id' => 'c3', 'name' => 'Kelengkapan Referensi', 'weight' => 20, 'order' => 3],
            ],
            'levels' => [
                ['id' => 'l1', 'level' => 1, 'label' => 'Poor', 'score_value' => 1],
                ['id' => 'l2', 'level' => 2, 'label' => 'Fair', 'score_value' => 2],
                ['id' => 'l3', 'level' => 3, 'label' => 'Good', 'score_value' => 3],
                ['id' => 'l4', 'level' => 4, 'label' => 'Excellent', 'score_value' => 4],
            ],
            'matrix' => [
                ['criteria_id' => 'c1', 'level_id' => 'l1', 'description' => 'Konsep banyak keliru atau tidak relevan.'],
                ['criteria_id' => 'c1', 'level_id' => 'l4', 'description' => 'Konsep tepat, kuat, dan konsisten.'],
                ['criteria_id' => 'c2', 'level_id' => 'l1', 'description' => 'Struktur tidak jelas, alur sulit diikuti.'],
                ['criteria_id' => 'c2', 'level_id' => 'l4', 'description' => 'Struktur runtut, logis, dan mudah dipahami.'],
                ['criteria_id' => 'c3', 'level_id' => 'l1', 'description' => 'Referensi minim atau tidak kredibel.'],
                ['criteria_id' => 'c3', 'level_id' => 'l4', 'description' => 'Referensi lengkap, relevan, dan valid.'],
            ],
        ];
    }

    private function buildRubricPayload(Rubric $rubric): array
    {
        $rubric->load([
            'mentor:id,name',
            'criteria',
            'levels',
        ]);

        $criteriaIds = $rubric->criteria->pluck('id')->all();
        $descriptions = count($criteriaIds)
            ? RubricDescription::query()->whereIn('criteria_id', $criteriaIds)->get()
            : collect();

        $matrix = [];
        foreach ($descriptions as $desc) {
            $matrix[(int) $desc->criteria_id][(int) $desc->level_id] = $desc->description;
        }

        return [
            'rubric' => [
                'id' => $rubric->id,
                'title' => $rubric->title,
                'description' => $rubric->description,
                'mentor_id' => $rubric->mentor_id,
                'mentor_name' => $rubric->mentor?->name,
                'max_score' => (float) $rubric->max_score,
                'created_at' => $rubric->created_at?->toISOString(),
                'updated_at' => $rubric->updated_at?->toISOString(),
            ],
            'criteria' => $rubric->criteria->map(fn ($c) => [
                'id' => $c->id,
                'rubric_id' => $c->rubric_id,
                'name' => $c->name,
                'weight' => (float) $c->weight,
                'order' => $c->order,
            ])->values()->all(),
            'levels' => $rubric->levels->map(fn ($l) => [
                'id' => $l->id,
                'rubric_id' => $l->rubric_id,
                'level' => $l->level,
                'label' => $l->label,
                'score_value' => (float) $l->score_value,
            ])->values()->all(),
            'matrix' => $matrix,
        ];
    }
}
