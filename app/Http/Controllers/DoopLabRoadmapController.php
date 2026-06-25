<?php

namespace App\Http\Controllers;

use App\Models\DoopLabRoadmap;
use App\Models\DoopLabRoadmapEdge;
use App\Models\DoopLabRoadmapEnrollment;
use App\Models\DoopLabRoadmapNode;
use App\Models\DoopLabRoadmapSection;
use App\Models\DoopLabRoadmapTextBlock;
use App\Models\Guide;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DoopLabRoadmapController extends Controller
{
    private function workspaceParams(Request $request, string $roadmapUuid): array
    {
        $params = ['roadmap' => $roadmapUuid];
        if ((string) $request->query('workspace', '') === '1' || (string) $request->input('workspace', '') === '1') {
            $params['workspace'] = 1;
        }

        return $params;
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->assertCanAccessRoadmapLab($user);

        $selectedRoadmapUuid = trim((string) $request->query('roadmap', ''));

        $roadmapListQuery = DoopLabRoadmap::query()
            ->when($user->isMentor(), fn ($query) => $query->where('created_by_user_id', (int) $user->id))
            ->withCount(['sections', 'nodes', 'edges'])
            ->latest('updated_at');

        $roadmaps = $roadmapListQuery
            ->get(['id', 'uuid', 'title', 'description', 'is_published', 'created_by_user_id', 'updated_at'])
            ->map(fn (DoopLabRoadmap $roadmap) => [
                'id' => (int) $roadmap->id,
                'uuid' => (string) $roadmap->uuid,
                'title' => (string) ($roadmap->title ?? ''),
                'description' => (string) ($roadmap->description ?? ''),
                'is_published' => (bool) $roadmap->is_published,
                'sections_count' => (int) ($roadmap->sections_count ?? 0),
                'nodes_count' => (int) ($roadmap->nodes_count ?? 0),
                'edges_count' => (int) ($roadmap->edges_count ?? 0),
                'updated_at' => optional($roadmap->updated_at)->toIso8601String(),
                'is_mine' => (int) $roadmap->created_by_user_id === (int) $user->id,
            ])
            ->values()
            ->all();

        $selectedRoadmap = $this->resolveSelectedRoadmap($user, $selectedRoadmapUuid);

        return Inertia::render('DoopLab/Roadmaps/Index', [
            'roadmaps' => $roadmaps,
            'activeRoadmap' => $selectedRoadmap ? $this->serializeRoadmap($selectedRoadmap) : null,
            'roadmapLabPermissions' => [
                'can_manage' => true,
                'is_admin' => (bool) $user->isAdmin(),
                'is_mentor' => (bool) $user->isMentor(),
            ],
            'availableGuides' => Guide::query()
                ->select('id', 'uuid', 'title')
                ->orderBy('title')
                ->get()
                ->map(fn (Guide $guide) => [
                    'id' => (int) $guide->id,
                    'uuid' => (string) $guide->uuid,
                    'title' => (string) ($guide->title ?? ''),
                ])
                ->values()
                ->all(),
            'availableQuests' => Quest::query()
                ->select('id', 'uuid', 'title')
                ->orderBy('title')
                ->get()
                ->map(fn (Quest $quest) => [
                    'id' => (int) $quest->id,
                    'uuid' => (string) $quest->uuid,
                    'title' => (string) ($quest->title ?? ''),
                ])
                ->values()
                ->all(),
            'assignableUsers' => User::query()
                ->where('role', User::ROLE_STUDENT)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => (int) $u->id,
                    'name' => (string) ($u->name ?? ''),
                    'email' => (string) ($u->email ?? ''),
                ])
                ->values()
                ->all(),
            'enrolledUsers' => $selectedRoadmap ? DoopLabRoadmapEnrollment::query()
                ->where('roadmap_id', $selectedRoadmap->id)
                ->with('user:id,name,email')
                ->get()
                ->map(fn (DoopLabRoadmapEnrollment $e) => [
                    'enrollment_uuid' => (string) $e->uuid,
                    'user_id' => (int) $e->user_id,
                    'name' => (string) ($e->user?->name ?? ''),
                    'email' => (string) ($e->user?->email ?? ''),
                    'status' => (string) $e->status,
                ])
                ->values()
                ->all() : [],
            'studentsOverview' => DoopLabRoadmapEnrollment::query()
                ->when(! $user->isAdmin(), fn ($query) => $query->where('mentor_user_id', (int) $user->id))
                ->with(['user:id,name,email,role', 'roadmap:id,uuid,title'])
                ->get()
                ->groupBy('user_id')
                ->map(function ($items) {
                    $first = $items->first();
                    return [
                        'user_id' => (int) ($first->user_id ?? 0),
                        'name' => (string) ($first->user?->name ?? ''),
                        'email' => (string) ($first->user?->email ?? ''),
                        'enrollments' => $items->map(fn ($e) => [
                            'enrollment_uuid' => (string) $e->uuid,
                            'roadmap_uuid' => (string) ($e->roadmap?->uuid ?? ''),
                            'roadmap_title' => (string) ($e->roadmap?->title ?? ''),
                            'status' => (string) $e->status,
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function storeRoadmap(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanAccessRoadmapLab($user);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $roadmap = DoopLabRoadmap::query()->create([
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'created_by_user_id' => (int) $user->id,
            'updated_by_user_id' => (int) $user->id,
        ]);

        return redirect()->route('dooplab.roadmaps.index', ['roadmap' => $roadmap->uuid])
            ->with('message', 'DOOPLAB_ROADMAP_CREATED');
    }

    public function updateRoadmap(Request $request, DoopLabRoadmap $roadmap): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $roadmap->update([
            'title' => trim((string) $validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'updated_by_user_id' => (int) $user->id,
        ]);

        return redirect()->route('dooplab.roadmaps.index', ['roadmap' => $roadmap->uuid])
            ->with('message', 'DOOPLAB_ROADMAP_UPDATED');
    }

    public function destroyRoadmap(Request $request, DoopLabRoadmap $roadmap): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageRoadmap($user, $roadmap);

        $roadmap->delete();

        return redirect()->route('dooplab.roadmaps.index')
            ->with('message', 'DOOPLAB_ROADMAP_DELETED');
    }

    public function storeSection(Request $request, DoopLabRoadmap $roadmap): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'x' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'y' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'width' => ['nullable', 'integer', 'min:200', 'max:3000'],
            'height' => ['nullable', 'integer', 'min:160', 'max:2400'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:120'],
            'text_align' => ['nullable', 'string', 'in:left,center,right'],
            'text_valign' => ['nullable', 'string', 'in:top,middle,bottom'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        DoopLabRoadmapSection::query()->create([
            'roadmap_id' => (int) $roadmap->id,
            'title' => trim((string) $validated['title']),
            'x' => (int) ($validated['x'] ?? 24),
            'y' => (int) ($validated['y'] ?? 24),
            'width' => (int) ($validated['width'] ?? 500),
            'height' => (int) ($validated['height'] ?? 260),
            'bg_color' => $this->normalizeColor((string) ($validated['bg_color'] ?? '#dbeafe'), '#dbeafe'),
            'text_color' => $this->normalizeColor((string) ($validated['text_color'] ?? '#1e3a8a'), '#1e3a8a'),
            'font_size' => (int) ($validated['font_size'] ?? 20),
            'text_align' => (string) ($validated['text_align'] ?? 'left'),
            'text_valign' => (string) ($validated['text_valign'] ?? 'top'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function updateSection(Request $request, DoopLabRoadmapSection $section): SymfonyResponse
    {
        $user = $request->user();
        $roadmap = $section->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'x' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'y' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'width' => ['nullable', 'integer', 'min:200', 'max:3000'],
            'height' => ['nullable', 'integer', 'min:160', 'max:2400'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:120'],
            'text_align' => ['nullable', 'string', 'in:left,center,right'],
            'text_valign' => ['nullable', 'string', 'in:top,middle,bottom'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $section->update([
            'title' => trim((string) $validated['title']),
            'x' => (int) ($validated['x'] ?? $section->x),
            'y' => (int) ($validated['y'] ?? $section->y),
            'width' => (int) ($validated['width'] ?? $section->width),
            'height' => (int) ($validated['height'] ?? $section->height),
            'bg_color' => $this->normalizeColor((string) ($validated['bg_color'] ?? $section->bg_color), '#dbeafe'),
            'text_color' => $this->normalizeColor((string) ($validated['text_color'] ?? $section->text_color), '#1e3a8a'),
            'font_size' => (int) ($validated['font_size'] ?? $section->font_size),
            'text_align' => (string) ($validated['text_align'] ?? $section->text_align),
            'text_valign' => (string) ($validated['text_valign'] ?? $section->text_valign),
            'sort_order' => (int) ($validated['sort_order'] ?? $section->sort_order),
        ]);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function destroySection(Request $request, DoopLabRoadmapSection $section): RedirectResponse
    {
        $user = $request->user();
        $roadmap = $section->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $section->delete();
        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function storeTextBlock(Request $request, DoopLabRoadmap $roadmap): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'x' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'y' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'width' => ['nullable', 'integer', 'min:120', 'max:2400'],
            'height' => ['nullable', 'integer', 'min:60', 'max:1600'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:120'],
            'text_align' => ['nullable', 'string', 'in:left,center,right'],
            'text_valign' => ['nullable', 'string', 'in:top,middle,bottom'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        DoopLabRoadmapTextBlock::query()->create([
            'roadmap_id' => (int) $roadmap->id,
            'content' => trim((string) $validated['content']),
            'x' => (int) ($validated['x'] ?? 120),
            'y' => (int) ($validated['y'] ?? 120),
            'width' => (int) ($validated['width'] ?? 320),
            'height' => (int) ($validated['height'] ?? 120),
            'bg_color' => $this->normalizeColor((string) ($validated['bg_color'] ?? 'transparent'), 'transparent'),
            'text_color' => $this->normalizeColor((string) ($validated['text_color'] ?? '#e6f6ff'), '#e6f6ff'),
            'font_size' => (int) ($validated['font_size'] ?? 16),
            'text_align' => (string) ($validated['text_align'] ?? 'left'),
            'text_valign' => (string) ($validated['text_valign'] ?? 'top'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function updateTextBlock(Request $request, DoopLabRoadmapTextBlock $textBlock): SymfonyResponse
    {
        $user = $request->user();
        $roadmap = $textBlock->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'x' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'y' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'width' => ['nullable', 'integer', 'min:120', 'max:2400'],
            'height' => ['nullable', 'integer', 'min:60', 'max:1600'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:120'],
            'text_align' => ['nullable', 'string', 'in:left,center,right'],
            'text_valign' => ['nullable', 'string', 'in:top,middle,bottom'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $textBlock->update([
            'content' => trim((string) $validated['content']),
            'x' => (int) ($validated['x'] ?? $textBlock->x),
            'y' => (int) ($validated['y'] ?? $textBlock->y),
            'width' => (int) ($validated['width'] ?? $textBlock->width),
            'height' => (int) ($validated['height'] ?? $textBlock->height),
            'bg_color' => $this->normalizeColor((string) ($validated['bg_color'] ?? $textBlock->bg_color), 'transparent'),
            'text_color' => $this->normalizeColor((string) ($validated['text_color'] ?? $textBlock->text_color), '#e6f6ff'),
            'font_size' => (int) ($validated['font_size'] ?? $textBlock->font_size),
            'text_align' => (string) ($validated['text_align'] ?? $textBlock->text_align),
            'text_valign' => (string) ($validated['text_valign'] ?? $textBlock->text_valign),
            'sort_order' => (int) ($validated['sort_order'] ?? $textBlock->sort_order),
        ]);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function destroyTextBlock(Request $request, DoopLabRoadmapTextBlock $textBlock): RedirectResponse
    {
        $user = $request->user();
        $roadmap = $textBlock->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $textBlock->delete();
        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function storeNode(Request $request, DoopLabRoadmap $roadmap): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'section_uuid' => ['nullable', 'string', 'max:100'],
            'x' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'y' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'width' => ['nullable', 'integer', 'min:120', 'max:1800'],
            'height' => ['nullable', 'integer', 'min:50', 'max:1200'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:120'],
            'text_align' => ['nullable', 'string', 'in:left,center,right'],
            'text_valign' => ['nullable', 'string', 'in:top,middle,bottom'],
            'resource_items' => ['nullable', 'array', 'max:30'],
            'resource_items.*.type' => ['required', 'string', 'in:guide,quest'],
            'resource_items.*.id' => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $sectionId = $this->resolveSectionIdInRoadmap($roadmap, (string) ($validated['section_uuid'] ?? ''));

        $resourceItems = $this->normalizeNodeResourceItems($validated['resource_items'] ?? []);
        $primaryResource = $resourceItems[0] ?? null;

        $node = DoopLabRoadmapNode::query()->create([
            'roadmap_id' => (int) $roadmap->id,
            'section_id' => $sectionId,
            'title' => trim((string) $validated['title']),
            'x' => (int) ($validated['x'] ?? 64),
            'y' => (int) ($validated['y'] ?? 64),
            'width' => (int) ($validated['width'] ?? 180),
            'height' => (int) ($validated['height'] ?? 72),
            'bg_color' => $this->normalizeColor((string) ($validated['bg_color'] ?? '#93c5fd'), '#93c5fd'),
            'text_color' => $this->normalizeColor((string) ($validated['text_color'] ?? '#0f172a'), '#0f172a'),
            'font_size' => (int) ($validated['font_size'] ?? 28),
            'text_align' => (string) ($validated['text_align'] ?? 'center'),
            'text_valign' => (string) ($validated['text_valign'] ?? 'middle'),
            'resource_type' => $primaryResource['type'] ?? null,
            'resource_id' => $primaryResource['id'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->syncNodeResourceItems($node, $resourceItems);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function updateNode(Request $request, DoopLabRoadmapNode $node): SymfonyResponse
    {
        $user = $request->user();
        $roadmap = $node->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'section_uuid' => ['nullable', 'string', 'max:100'],
            'x' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'y' => ['nullable', 'integer', 'min:0', 'max:8000'],
            'width' => ['nullable', 'integer', 'min:120', 'max:1800'],
            'height' => ['nullable', 'integer', 'min:50', 'max:1200'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:120'],
            'text_align' => ['nullable', 'string', 'in:left,center,right'],
            'text_valign' => ['nullable', 'string', 'in:top,middle,bottom'],
            'resource_items' => ['nullable', 'array', 'max:30'],
            'resource_items.*.type' => ['required', 'string', 'in:guide,quest'],
            'resource_items.*.id' => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $sectionId = $this->resolveSectionIdInRoadmap($roadmap, (string) ($validated['section_uuid'] ?? ''));
        $resourceItems = $this->normalizeNodeResourceItems($validated['resource_items'] ?? []);
        $primaryResource = $resourceItems[0] ?? null;

        $node->update([
            'section_id' => $sectionId,
            'title' => trim((string) $validated['title']),
            'x' => (int) ($validated['x'] ?? $node->x),
            'y' => (int) ($validated['y'] ?? $node->y),
            'width' => (int) ($validated['width'] ?? $node->width),
            'height' => (int) ($validated['height'] ?? $node->height),
            'bg_color' => $this->normalizeColor((string) ($validated['bg_color'] ?? $node->bg_color), '#93c5fd'),
            'text_color' => $this->normalizeColor((string) ($validated['text_color'] ?? $node->text_color), '#0f172a'),
            'font_size' => (int) ($validated['font_size'] ?? $node->font_size),
            'text_align' => (string) ($validated['text_align'] ?? $node->text_align),
            'text_valign' => (string) ($validated['text_valign'] ?? $node->text_valign),
            'resource_type' => $primaryResource['type'] ?? null,
            'resource_id' => $primaryResource['id'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? $node->sort_order),
        ]);

        $this->syncNodeResourceItems($node, $resourceItems);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function destroyNode(Request $request, DoopLabRoadmapNode $node): RedirectResponse
    {
        $user = $request->user();
        $roadmap = $node->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $node->delete();
        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function storeEdge(Request $request, DoopLabRoadmap $roadmap): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanManageRoadmap($user, $roadmap);

        $validated = $request->validate([
            'from_node_uuid' => ['required', 'string', 'max:100'],
            'to_node_uuid' => ['required', 'string', 'max:100', 'different:from_node_uuid'],
            'stroke_color' => ['nullable', 'string', 'max:20'],
            'curvature' => ['nullable', 'numeric', 'min:0.1', 'max:0.9'],
        ]);

        $fromNode = DoopLabRoadmapNode::query()
            ->where('roadmap_id', (int) $roadmap->id)
            ->where('uuid', (string) $validated['from_node_uuid'])
            ->firstOrFail();

        $toNode = DoopLabRoadmapNode::query()
            ->where('roadmap_id', (int) $roadmap->id)
            ->where('uuid', (string) $validated['to_node_uuid'])
            ->firstOrFail();

        $alreadyExists = DoopLabRoadmapEdge::query()
            ->where('roadmap_id', (int) $roadmap->id)
            ->where('from_node_id', (int) $fromNode->id)
            ->where('to_node_id', (int) $toNode->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'to_node_uuid' => 'Koneksi antar node tersebut sudah ada.',
            ]);
        }

        DoopLabRoadmapEdge::query()->create([
            'roadmap_id' => (int) $roadmap->id,
            'from_node_id' => (int) $fromNode->id,
            'to_node_id' => (int) $toNode->id,
            'stroke_color' => $this->normalizeColor((string) ($validated['stroke_color'] ?? '#334155'), '#334155'),
            'curvature' => round((float) ($validated['curvature'] ?? 0.35), 2),
        ]);

        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    public function destroyEdge(Request $request, DoopLabRoadmapEdge $edge): RedirectResponse
    {
        $user = $request->user();
        $roadmap = $edge->roadmap;
        $this->assertCanManageRoadmap($user, $roadmap);

        $edge->delete();
        $roadmap->update(['updated_by_user_id' => (int) $user->id]);

        return redirect()->route('dooplab.roadmaps.index', $this->workspaceParams($request, $roadmap->uuid));
    }

    private function assertCanAccessRoadmapLab(?User $user): void
    {
        abort_unless($user && $user->canAccessDoopLab(), 403, 'DOOPLAB_ACCESS_DENIED');
        abort_unless($user->isMentor() || $user->isAdmin(), 403, 'ROADMAP_LAB_MENTOR_ONLY');
    }

    private function assertCanManageRoadmap(?User $user, DoopLabRoadmap $roadmap): void
    {
        $this->assertCanAccessRoadmapLab($user);

        if ($user->isAdmin()) {
            return;
        }

        abort_unless((int) $roadmap->created_by_user_id === (int) $user->id, 403, 'ROADMAP_LAB_FORBIDDEN');
    }

    private function resolveSelectedRoadmap(User $user, string $selectedRoadmapUuid): ?DoopLabRoadmap
    {
        $query = DoopLabRoadmap::query()
            ->when($user->isMentor(), fn ($q) => $q->where('created_by_user_id', (int) $user->id))
            ->with([
                'sections' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'nodes' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'nodes.resources',
                'textBlocks' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'edges.fromNode:id,uuid',
                'edges.toNode:id,uuid',
            ]);

        if ($selectedRoadmapUuid !== '') {
            $selected = (clone $query)->where('uuid', $selectedRoadmapUuid)->first();
            if ($selected) {
                return $selected;
            }
        }

        return $query->latest('updated_at')->first();
    }

    private function serializeRoadmap(DoopLabRoadmap $roadmap): array
    {
        return [
            'id' => (int) $roadmap->id,
            'uuid' => (string) $roadmap->uuid,
            'title' => (string) ($roadmap->title ?? ''),
            'description' => (string) ($roadmap->description ?? ''),
            'is_published' => (bool) $roadmap->is_published,
            'sections' => $roadmap->sections
                ->map(fn (DoopLabRoadmapSection $section) => [
                    'id' => (int) $section->id,
                    'uuid' => (string) $section->uuid,
                    'title' => (string) ($section->title ?? ''),
                    'x' => (int) $section->x,
                    'y' => (int) $section->y,
                    'width' => (int) $section->width,
                    'height' => (int) $section->height,
                    'bg_color' => (string) ($section->bg_color ?? '#dbeafe'),
                    'text_color' => (string) ($section->text_color ?? '#1e3a8a'),
                    'font_size' => (int) ($section->font_size ?? 20),
                    'text_align' => (string) ($section->text_align ?? 'left'),
                    'text_valign' => (string) ($section->text_valign ?? 'top'),
                    'sort_order' => (int) $section->sort_order,
                ])
                ->values()
                ->all(),
            'nodes' => $roadmap->nodes
                ->map(fn (DoopLabRoadmapNode $node) => [
                    'id' => (int) $node->id,
                    'uuid' => (string) $node->uuid,
                    'section_id' => (int) ($node->section_id ?? 0),
                    'title' => (string) ($node->title ?? ''),
                    'x' => (int) $node->x,
                    'y' => (int) $node->y,
                    'width' => (int) $node->width,
                    'height' => (int) $node->height,
                    'bg_color' => (string) ($node->bg_color ?? '#93c5fd'),
                    'text_color' => (string) ($node->text_color ?? '#0f172a'),
                    'font_size' => (int) ($node->font_size ?? 28),
                    'text_align' => (string) ($node->text_align ?? 'center'),
                    'text_valign' => (string) ($node->text_valign ?? 'middle'),
                    'resource_type' => (string) ($node->resource_type ?? ''),
                    'resource_id' => $node->resource_id ? (int) $node->resource_id : null,
                    'resource_items' => $node->resources->map(fn ($r) => [
                        'type' => (string) $r->resource_type,
                        'id' => (int) $r->resource_id,
                    ])->values()->all(),
                    'sort_order' => (int) $node->sort_order,
                ])
                ->values()
                ->all(),
            'text_blocks' => $roadmap->textBlocks
                ->map(fn (DoopLabRoadmapTextBlock $textBlock) => [
                    'id' => (int) $textBlock->id,
                    'uuid' => (string) $textBlock->uuid,
                    'content' => (string) ($textBlock->content ?? ''),
                    'x' => (int) $textBlock->x,
                    'y' => (int) $textBlock->y,
                    'width' => (int) $textBlock->width,
                    'height' => (int) $textBlock->height,
                    'bg_color' => (string) ($textBlock->bg_color ?? 'transparent'),
                    'text_color' => (string) ($textBlock->text_color ?? '#e6f6ff'),
                    'font_size' => (int) ($textBlock->font_size ?? 16),
                    'text_align' => (string) ($textBlock->text_align ?? 'left'),
                    'text_valign' => (string) ($textBlock->text_valign ?? 'top'),
                    'sort_order' => (int) $textBlock->sort_order,
                ])
                ->values()
                ->all(),
            'edges' => $roadmap->edges
                ->filter(fn (DoopLabRoadmapEdge $edge) => $edge->fromNode && $edge->toNode)
                ->map(fn (DoopLabRoadmapEdge $edge) => [
                    'id' => (int) $edge->id,
                    'uuid' => (string) $edge->uuid,
                    'from_node_uuid' => (string) ($edge->fromNode?->uuid ?? ''),
                    'to_node_uuid' => (string) ($edge->toNode?->uuid ?? ''),
                    'stroke_color' => (string) ($edge->stroke_color ?? '#334155'),
                    'curvature' => (float) ($edge->curvature ?? 0.35),
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveSectionIdInRoadmap(DoopLabRoadmap $roadmap, string $sectionUuid): ?int
    {
        $trimmed = trim($sectionUuid);
        if ($trimmed === '') {
            return null;
        }

        $section = DoopLabRoadmapSection::query()
            ->where('roadmap_id', (int) $roadmap->id)
            ->where('uuid', $trimmed)
            ->first();

        if (! $section) {
            throw ValidationException::withMessages([
                'section_uuid' => 'Section tidak ditemukan pada roadmap terpilih.',
            ]);
        }

        return (int) $section->id;
    }

    private function normalizeColor(string $value, string $fallback): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $fallback;
        }

        if (strtolower($trimmed) === 'transparent') {
            return 'transparent';
        }

        if (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $trimmed) !== 1) {
            return $fallback;
        }

        return strtolower($trimmed);
    }

    private function normalizeNodeResourceItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $type = trim((string) ($item['type'] ?? ''));
            $id = (int) ($item['id'] ?? 0);
            if ($type === '' || $id <= 0) continue;
            if (! in_array($type, ['guide', 'quest'], true)) continue;
            $result[] = ['type' => $type, 'id' => $id];
        }
        return $result;
    }

    private function syncNodeResourceItems(DoopLabRoadmapNode $node, array $items): void
    {
        $node->resources()->delete();

        foreach ($items as $idx => $item) {
            $node->resources()->create([
                'resource_type' => $item['type'],
                'resource_id' => $item['id'],
                'sort_order' => $idx,
            ]);
        }
    }
}
