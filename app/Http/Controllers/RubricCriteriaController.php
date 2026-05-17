<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rubrics\StoreRubricCriterionRequest;
use App\Http\Requests\Rubrics\UpdateRubricCriterionRequest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Services\RubricScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RubricCriteriaController extends Controller
{
    public function store(StoreRubricCriterionRequest $request, Rubric $rubric, RubricScoringService $scoring): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $criterion = DB::transaction(function () use ($rubric, $validated, $scoring) {
            $nextOrder = (int) (RubricCriterion::query()->where('rubric_id', $rubric->id)->max('order') ?? 0);
            $order = array_key_exists('order', $validated) && $validated['order'] !== null
                ? (int) $validated['order']
                : $nextOrder + 1;

            $criterion = RubricCriterion::create([
                'rubric_id' => $rubric->id,
                'name' => $validated['name'],
                'weight' => $validated['weight'],
                'order' => $order,
            ]);

            $rubric->update([
                'max_score' => $scoring->calculateMaxScore($rubric->fresh()),
            ]);

            return $criterion;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'criterion' => [
                    'id' => $criterion->id,
                    'rubric_id' => $criterion->rubric_id,
                    'name' => $criterion->name,
                    'weight' => (float) $criterion->weight,
                    'order' => $criterion->order,
                ],
            ]);
        }

        return back()->with('message', 'RUBRIC_CRITERION_CREATED');
    }

    public function update(UpdateRubricCriterionRequest $request, Rubric $rubric, RubricCriterion $criterion, RubricScoringService $scoring): RedirectResponse|JsonResponse
    {
        abort_unless((int) $criterion->rubric_id === (int) $rubric->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($rubric, $criterion, $validated, $scoring) {
            $criterion->update([
                'name' => $validated['name'],
                'weight' => $validated['weight'],
                'order' => (int) $validated['order'],
            ]);

            $rubric->update([
                'max_score' => $scoring->calculateMaxScore($rubric->fresh()),
            ]);
        });

        if ($request->wantsJson()) {
            $criterion->refresh();
            return response()->json([
                'criterion' => [
                    'id' => $criterion->id,
                    'rubric_id' => $criterion->rubric_id,
                    'name' => $criterion->name,
                    'weight' => (float) $criterion->weight,
                    'order' => $criterion->order,
                ],
            ]);
        }

        return back()->with('message', 'RUBRIC_CRITERION_UPDATED');
    }

    public function destroy(Rubric $rubric, RubricCriterion $criterion, RubricScoringService $scoring): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $rubric);
        abort_unless((int) $criterion->rubric_id === (int) $rubric->id, 404);

        DB::transaction(function () use ($rubric, $criterion, $scoring) {
            $criterion->delete();
            $rubric->update([
                'max_score' => $scoring->calculateMaxScore($rubric->fresh()),
            ]);
        });

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('message', 'RUBRIC_CRITERION_DELETED');
    }
}
