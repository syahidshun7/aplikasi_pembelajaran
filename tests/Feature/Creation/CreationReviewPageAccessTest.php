<?php

use App\Models\Creation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('review result page is accessible for public creation', function () {
    $owner = User::factory()->create();

    $creation = Creation::query()->create([
        'user_id' => $owner->id,
        'title' => 'Public Creation',
        'description' => 'Public creation description',
        'status' => 'finished',
        'progress' => 100,
        'is_public' => true,
    ]);

    $this->get(route('hall.creations.review', ['creation' => $creation->id]))
        ->assertOk();
});

test('review result page returns not found for private creation to outsider', function () {
    $owner = User::factory()->create();

    $creation = Creation::query()->create([
        'user_id' => $owner->id,
        'title' => 'Private Creation',
        'description' => 'Private creation description',
        'status' => 'finished',
        'progress' => 100,
        'is_public' => false,
    ]);

    $this->get(route('hall.creations.review', ['creation' => $creation->id]))
        ->assertNotFound();
});

