<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\DoopNewsPost;
use App\Models\Guide;
use App\Models\JobRole;
use App\Models\ProfileSkin;
use App\Models\Quest;
use App\Models\Rubric;
use App\Models\ShopItem;
use App\Models\StudyGroup;
use App\Models\Submission;
use App\Models\TaskBank;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AdminTrashRedesignController extends Controller
{
    public function index(Request $request): Response
    {
        $types = [
            'quest' => [Quest::class, 'title', 'quests.restore', 'quests.force-destroy'],
            'materi' => [Guide::class, 'title', 'materi.restore', 'materi.force-destroy'],
            'event' => [Event::class, 'title', 'admin.events.restore', 'admin.events.force-destroy'],
            'kelas' => [StudyGroup::class, 'name', 'groups.restore', 'groups.force-destroy'],
            'pengguna' => [User::class, 'name', 'admin.users.restore', 'admin.users.force-destroy'],
            'submission' => [Submission::class, 'uuid', 'admin.submissions.manage.restore', 'admin.submissions.manage.force-destroy'],
        ];
        $items = collect();
        foreach ($types as $type => [$model, $label, $restore, $destroy]) {
            $items = $items->concat($model::onlyTrashed()->latest('deleted_at')->limit(100)->get()
                ->map(fn ($row) => [
                    'id' => $type.':'.$row->getKey(),
                    'name' => (string) ($row->{$label} ?: $row->getKey()),
                    'type' => $type,
                    'deleted_at' => $row->deleted_at?->toISOString(),
                    'restore_url' => route($restore, $row->getRouteKey()),
                    'destroy_url' => route($destroy, $row->getRouteKey()),
                ]));
        }
        foreach ($this->additionalTypes() as $type => [$model, $label]) {
            $items = $items->concat($model::onlyTrashed()->latest('deleted_at')->limit(100)->get()
                ->map(fn ($row) => [
                    'id' => $type.':'.$row->getKey(),
                    'name' => (string) ($row->{$label} ?: $row->getKey()),
                    'type' => $type,
                    'deleted_at' => $row->deleted_at?->toISOString(),
                    'restore_url' => route('redesign.trash.restore', [$type, $row->getKey()]),
                    'destroy_url' => route('redesign.trash.force-destroy', [$type, $row->getKey()]),
                ]));
        }
        return Inertia::render('Redesign/AdminDashboard/Trash', [
            'items' => $items->sortByDesc('deleted_at')->values()->all(),
        ]);
    }

    public function restore(string $type, string $id): RedirectResponse
    {
        [$model] = $this->additionalTypes()[$type] ?? abort(404);
        $model::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('message', 'ITEM_RESTORED');
    }

    public function forceDestroy(string $type, string $id): RedirectResponse
    {
        [$model] = $this->additionalTypes()[$type] ?? abort(404);
        $model::onlyTrashed()->findOrFail($id)->forceDelete();
        return back()->with('message', 'ITEM_DELETED_PERMANENTLY');
    }

    private function additionalTypes(): array
    {
        return [
            'program' => [JobRole::class, 'name'],
            'task_bank' => [TaskBank::class, 'name'],
            'rubrik' => [Rubric::class, 'title'],
            'doopnews' => [DoopNewsPost::class, 'title'],
            'shop_item' => [ShopItem::class, 'name'],
            'profile_skin' => [ProfileSkin::class, 'name'],
        ];
    }
}
