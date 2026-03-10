<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rubrics\StoreRubricRequest;
use App\Http\Requests\Rubrics\UpdateRubricRequest;
use App\Models\Rubric;
use App\Models\RubricDescription;
use App\Models\RubricLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
