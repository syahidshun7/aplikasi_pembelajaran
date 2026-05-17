<?php

namespace App\Http\Controllers;

use App\Models\DailyQuestDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDailyQuestDefinitionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'activity_type' => ['nullable', Rule::in(array_merge(['all'], $this->activityTypes()))],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $activityType = (string) ($validated['activity_type'] ?? 'all');

        $definitions = DailyQuestDefinition::query()
            ->when($activityType !== '' && $activityType !== 'all', function ($query) use ($activityType) {
                $query->where('activity_type', $activityType);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('activity_type', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(12)
            ->through(function (DailyQuestDefinition $definition) {
                $meta = is_array($definition->meta) ? $definition->meta : [];
                $activitySteps = collect($meta['activity_steps'] ?? [])
                    ->filter(fn ($step) => is_string($step) && trim($step) !== '')
                    ->map(fn (string $step) => trim($step))
                    ->values()
                    ->all();

                return [
                    'id' => (int) $definition->id,
                    'code' => (string) $definition->code,
                    'title' => (string) $definition->title,
                    'description' => (string) ($definition->description ?? ''),
                    'activity_type' => (string) $definition->activity_type,
                    'target_value' => (int) ($definition->target_value ?? 1),
                    'reward_exp' => (int) ($definition->reward_exp ?? 0),
                    'reward_gold' => (int) ($definition->reward_gold ?? 0),
                    'sort_order' => (int) ($definition->sort_order ?? 1),
                    'is_active' => (bool) $definition->is_active,
                    'activity_steps' => $activitySteps,
                    'activity_steps_text' => implode(PHP_EOL, $activitySteps),
                ];
            })
            ->withQueryString();

        $stats = [
            'total' => DailyQuestDefinition::query()->count(),
            'active' => DailyQuestDefinition::query()->where('is_active', true)->count(),
            'inactive' => DailyQuestDefinition::query()->where('is_active', false)->count(),
        ];

        return Inertia::render('DailyQuests/Admin/Index', [
            'definitions' => $definitions,
            'stats' => $stats,
            'activityTypes' => $this->activityTypes(),
            'filters' => [
                'search' => $search,
                'activity_type' => $activityType,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        DailyQuestDefinition::query()->create([
            'code' => strtolower((string) $validated['code']),
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'activity_type' => (string) $validated['activity_type'],
            'target_value' => (int) $validated['target_value'],
            'reward_exp' => (int) $validated['reward_exp'],
            'reward_gold' => (int) $validated['reward_gold'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) $validated['is_active'],
            'meta' => $this->buildMetaPayload($validated),
        ]);

        return back()->with('message', 'DAILY_QUEST_DEFINITION_CREATED');
    }

    public function update(Request $request, DailyQuestDefinition $definition): RedirectResponse
    {
        $validated = $this->validatePayload($request, $definition);

        $definition->update([
            'code' => strtolower((string) $validated['code']),
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'activity_type' => (string) $validated['activity_type'],
            'target_value' => (int) $validated['target_value'],
            'reward_exp' => (int) $validated['reward_exp'],
            'reward_gold' => (int) $validated['reward_gold'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) $validated['is_active'],
            'meta' => $this->buildMetaPayload($validated, $definition),
        ]);

        return back()->with('message', 'DAILY_QUEST_DEFINITION_UPDATED');
    }

    public function destroy(DailyQuestDefinition $definition): RedirectResponse
    {
        $hasUsageHistory = $definition->dailyQuests()->exists();
        if ($hasUsageHistory) {
            return back()->withErrors([
                'daily_quest_definition' => 'Definition ini sudah punya histori quest. Nonaktifkan saja agar reward berhenti dipakai.',
            ]);
        }

        $definition->delete();

        return back()->with('message', 'DAILY_QUEST_DEFINITION_DELETED');
    }

    private function validatePayload(Request $request, ?DailyQuestDefinition $definition = null): array
    {
        $ruleUniqueCode = Rule::unique('daily_quest_definitions', 'code');
        if ($definition) {
            $ruleUniqueCode = $ruleUniqueCode->ignore($definition->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', $ruleUniqueCode],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activity_type' => ['required', Rule::in($this->activityTypes())],
            'target_value' => ['required', 'integer', 'min:1', 'max:1000'],
            'reward_exp' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reward_gold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'activity_steps_text' => ['nullable', 'string', 'max:3000'],
            'meta_category' => ['nullable', 'string', 'max:120'],
            'meta_icon' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function buildMetaPayload(array $validated, ?DailyQuestDefinition $existing = null): array
    {
        $baseMeta = $existing && is_array($existing->meta)
            ? $existing->meta
            : [];

        $steps = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['activity_steps_text'] ?? '')))
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values()
            ->all();

        if ($steps === []) {
            unset($baseMeta['activity_steps']);
        } else {
            $baseMeta['activity_steps'] = $steps;
        }

        $metaCategory = trim((string) ($validated['meta_category'] ?? ''));
        if ($metaCategory !== '') {
            $baseMeta['category'] = $metaCategory;
        }

        $metaIcon = trim((string) ($validated['meta_icon'] ?? ''));
        if ($metaIcon !== '') {
            $baseMeta['icon'] = $metaIcon;
        }

        return $baseMeta;
    }

    private function activityTypes(): array
    {
        return [
            DailyQuestDefinition::ACTIVITY_LOGIN,
            DailyQuestDefinition::ACTIVITY_QUEST_SUBMISSION,
            DailyQuestDefinition::ACTIVITY_EVENT_ATTENDANCE,
        ];
    }
}
