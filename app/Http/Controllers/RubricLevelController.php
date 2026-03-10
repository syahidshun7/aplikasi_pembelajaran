<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rubrics\StoreRubricLevelRequest;
use App\Http\Requests\Rubrics\UpdateRubricLevelRequest;
use App\Models\Rubric;
use App\Models\RubricLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RubricLevelController extends Controller
{
    public function store(StoreRubricLevelRequest $request, Rubric $rubric): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $level = DB::transaction(function () use ($rubric, $validated) {
            return RubricLevel::create([
                'rubric_id' => $rubric->id,
                'level' => (int) $validated['level'],
                'label' => $validated['label'],
                'score_value' => $validated['score_value'],
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'level' => [
                    'id' => $level->id,
                    'rubric_id' => $level->rubric_id,
                    'level' => $level->level,
                    'label' => $level->label,
                    'score_value' => (float) $level->score_value,
                ],
            ]);
        }

        return back()->with('message', 'RUBRIC_LEVEL_CREATED');
    }

    public function update(UpdateRubricLevelRequest $request, Rubric $rubric, RubricLevel $level): RedirectResponse|JsonResponse
    {
        abort_unless((int) $level->rubric_id === (int) $rubric->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($level, $validated) {
            $level->update([
                'level' => (int) $validated['level'],
                'label' => $validated['label'],
                'score_value' => $validated['score_value'],
            ]);
        });

        if ($request->wantsJson()) {
            $level->refresh();
            return response()->json([
                'level' => [
                    'id' => $level->id,
                    'rubric_id' => $level->rubric_id,
                    'level' => $level->level,
                    'label' => $level->label,
                    'score_value' => (float) $level->score_value,
                ],
            ]);
        }

        return back()->with('message', 'RUBRIC_LEVEL_UPDATED');
    }

    public function destroy(Rubric $rubric, RubricLevel $level): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $rubric);
        abort_unless((int) $level->rubric_id === (int) $rubric->id, 404);

        $level->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('message', 'RUBRIC_LEVEL_DELETED');
    }
}
