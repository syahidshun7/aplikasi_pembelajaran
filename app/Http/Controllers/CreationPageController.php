<?php

namespace App\Http\Controllers;

use App\Models\Creation;
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

    public function show(Request $request, Creation $creation): Response
    {
        $viewerId = (int) ($request->user()?->id ?? 0);
        $canView = (bool) $creation->is_public || ((int) $creation->user_id === $viewerId && $viewerId > 0);
        abort_unless($canView, 404, 'CREATION_NOT_FOUND');

        return Inertia::render('Creations/Show', [
            'creationId' => (int) $creation->id,
        ]);
    }
}
