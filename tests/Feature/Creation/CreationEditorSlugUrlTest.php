<?php

use App\Models\Creation;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('uses the creation slug as the canonical editor URL', function () {
    $owner = User::factory()->create();
    $creation = Creation::query()->create([
        'user_id' => $owner->id,
        'title' => 'Cosmic Orbit Portfolio',
        'description' => 'Slug editor URL test.',
        'status' => 'finished',
        'progress' => 100,
        'is_public' => true,
    ]);

    $slugUrl = route('profile.creations.edit', ['creation' => $creation->slug]);

    expect($slugUrl)->toEndWith('/profile/creations/cosmic-orbit-portfolio/edit');

    $this->actingAs($owner)
        ->get($slugUrl)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Creations/Editor')
            ->where('mode', 'edit')
            ->where('creationId', $creation->id)
            ->where('creationSlug', $creation->slug)
        );

    $this->actingAs($owner)
        ->get(route('profile.creations.edit', ['creation' => $creation->id]))
        ->assertRedirect($slugUrl)
        ->assertStatus(301);
});
