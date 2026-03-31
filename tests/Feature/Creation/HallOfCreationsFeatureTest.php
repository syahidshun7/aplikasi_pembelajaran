<?php

use App\Models\Creation;
use App\Models\CreationAppreciation;
use App\Models\User;
use App\Notifications\CreationAppreciatedNotification;
use App\Notifications\CreationInsightAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeCreation(User $owner, array $overrides = []): Creation
{
    return Creation::query()->create([
        'user_id' => $owner->id,
        'title' => 'Build Pixel App',
        'description' => 'A game-inspired LMS utility module.',
        'link' => 'https://example.com/demo',
        'category' => 'Web',
        'status' => 'crafting',
        'progress' => 45,
        'is_public' => true,
        ...$overrides,
    ]);
}

test('hall endpoint only returns public creations', function () {
    $owner = User::factory()->create();
    $publicCreation = makeCreation($owner, ['title' => 'Public Build', 'is_public' => true]);
    makeCreation($owner, ['title' => 'Private Build', 'is_public' => false]);

    $response = $this->getJson(route('api.hall.index'));

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $publicCreation->id,
        'title' => 'Public Build',
    ]);
    $response->assertJsonMissing([
        'title' => 'Private Build',
    ]);
});

test('appreciation is unique per user and notifies owner once', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $adventurer = User::factory()->create();
    $creation = makeCreation($owner);

    $first = $this
        ->actingAs($adventurer)
        ->postJson(route('api.creations.appreciate.store', ['creation' => $creation->id]));

    $first->assertOk()->assertJson([
        'appreciated' => true,
    ]);

    $second = $this
        ->actingAs($adventurer)
        ->postJson(route('api.creations.appreciate.store', ['creation' => $creation->id]));

    $second->assertOk()->assertJson([
        'appreciated' => true,
    ]);

    expect(CreationAppreciation::query()
        ->where('creation_id', $creation->id)
        ->where('user_id', $adventurer->id)
        ->count())->toBe(1);

    Notification::assertSentToTimes($owner, CreationAppreciatedNotification::class, 1);
    Notification::assertNotSentTo($adventurer, CreationAppreciatedNotification::class);
});

test('posting an insight notifies owner and supports replies', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $adventurer = User::factory()->create();
    $creation = makeCreation($owner);

    $insightResponse = $this
        ->actingAs($adventurer)
        ->postJson(route('api.creations.insights.store', ['creation' => $creation->id]), [
            'content' => 'Great progress! Keep refining your UX.',
        ]);

    $insightResponse->assertCreated();

    $insightId = (int) $insightResponse->json('data.id');
    expect($insightId)->toBeGreaterThan(0);

    $replyResponse = $this
        ->actingAs($owner)
        ->postJson(route('api.creations.insights.store', ['creation' => $creation->id]), [
            'content' => 'Thanks for the feedback.',
            'parent_id' => $insightId,
        ]);

    $replyResponse->assertCreated();

    Notification::assertSentTo($owner, CreationInsightAddedNotification::class);
    Notification::assertNotSentTo($owner, CreationAppreciatedNotification::class);
});

test('creation can store multiple photos and expose thumbnail', function () {
    Storage::fake('public');

    $owner = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->post(route('api.creations.store'), [
            'title' => 'Photo Build',
            'description' => 'Creation with gallery',
            'link' => 'https://example.com/gallery',
            'category' => 'Design',
            'status' => 'refining',
            'progress' => 70,
            'is_public' => true,
            'photos' => [
                UploadedFile::fake()->image('photo-a.jpg'),
                UploadedFile::fake()->image('photo-b.jpg'),
            ],
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.photos_count', 2);
    expect((string) $response->json('data.thumbnail_url'))->not()->toBe('');

    $creation = Creation::query()->firstOrFail();
    $paths = $creation->photos()->pluck('path')->all();

    expect($paths)->toHaveCount(2);

    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('creation update accepts png screenshot uploads', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $creation = makeCreation($owner);

    $response = $this
        ->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->post(route('api.creations.update', ['creation' => $creation->id]), [
            '_method' => 'PUT',
            'title' => 'Updated Screenshot Build',
            'description' => 'Updated with a PNG screenshot.',
            'link' => 'https://example.com/screenshot',
            'category' => 'Design',
            'status' => 'refining',
            'progress' => 85,
            'is_public' => true,
            'photos' => [
                UploadedFile::fake()->image('screenshot.png', 1600, 900),
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'Updated Screenshot Build');
    $response->assertJsonPath('data.photos_count', 1);

    $creation->refresh();
    $path = $creation->photos()->value('path');

    expect($path)->not()->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('creation validation returns a clear title length message', function () {
    $owner = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->post(route('api.creations.store'), [
            'title' => str_repeat('A', 256),
            'description' => 'Creation title is too long.',
            'status' => 'crafting',
            'progress' => 10,
            'is_public' => true,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('title');
    $response->assertJsonPath('errors.title.0', 'Judul creation maksimal 255 karakter.');
});

test('creation validation returns a clear photo format message', function () {
    $owner = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->post(route('api.creations.store'), [
            'title' => 'Invalid Photo Build',
            'description' => 'Creation with invalid file format.',
            'status' => 'crafting',
            'progress' => 25,
            'is_public' => true,
            'photos' => [
                UploadedFile::fake()->create('notes.txt', 64, 'text/plain'),
            ],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('photos.0');
    $errors = $response->json('errors');

    expect($errors['photos.0'][0] ?? null)->toBe('Foto harus berformat JPG, JPEG, PNG, atau WEBP.');
});
