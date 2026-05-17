<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\CreationCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreationPageController extends Controller
{
    public function hallIndex(): Response
    {
        return Inertia::render('Creations/HallOfCreationsPage');
    }

    public function index(): Response
    {
        return $this->profileCreations();
    }

    public function profileCreations(): Response
    {
        return Inertia::render('Profile/Creations');
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Creations/Editor', [
            'mode' => 'create',
            'creationId' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function edit(Request $request, Creation $creation): Response
    {
        $viewerId = (int) ($request->user()?->id ?? 0);
        abort_unless($creation->canEdit($viewerId), 403, 'CREATION_ACCESS_DENIED');

        return Inertia::render('Creations/Editor', [
            'mode' => 'edit',
            'creationId' => (int) $creation->id,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function show(Request $request, Creation $creation): Response
    {
        $viewerId = (int) ($request->user()?->id ?? 0);
        abort_unless($creation->canView($viewerId), 404, 'CREATION_NOT_FOUND');

        return Inertia::render('Creations/Show', [
            'creationId' => (int) $creation->id,
        ]);
    }

    public function showReview(Request $request, Creation $creation): Response
    {
        $viewerId = (int) ($request->user()?->id ?? 0);
        abort_unless($creation->canView($viewerId), 404, 'CREATION_NOT_FOUND');

        return Inertia::render('Creations/ReviewResult', [
            'creationId' => (int) $creation->id,
        ]);
    }

    private function categoryOptions(): array
    {
        return CreationCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (CreationCategory $category) => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ])
            ->values()
            ->all();
    }
}
