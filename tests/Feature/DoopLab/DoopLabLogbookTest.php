<?php

use App\Models\DoopLabLogbook;
use App\Models\DoopLabLogbookEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('logbook entry accepts up to five documentation photos', function () {
    Storage::fake('public');

    $mentor = User::factory()->create(['role' => User::ROLE_MENTOR]);
    $logbook = DoopLabLogbook::query()->create([
        'owner_user_id' => $mentor->id,
        'title' => 'PKL Harian',
    ]);

    $files = collect(range(1, 5))
        ->map(fn ($i) => UploadedFile::fake()->image("foto-{$i}.jpg"))
        ->all();

    $this->actingAs($mentor)
        ->post(route('dooplab.logbooks.entries.store', $logbook->uuid), [
            'activity_date' => '2026-06-30',
            'activity_time' => '09:00',
            'activity' => 'Dokumentasi progress',
            'documentation' => $files,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $entry = DoopLabLogbookEntry::query()->latest('id')->firstOrFail();

    expect($entry->documentation_paths)->toHaveCount(5);
    expect($entry->documentation_path)->toBe($entry->documentation_paths[0]);

    foreach ($entry->documentation_paths as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('logbook entry rejects more than five documentation photos', function () {
    Storage::fake('public');

    $mentor = User::factory()->create(['role' => User::ROLE_MENTOR]);
    $logbook = DoopLabLogbook::query()->create([
        'owner_user_id' => $mentor->id,
        'title' => 'PKL Harian',
    ]);

    $files = collect(range(1, 6))
        ->map(fn ($i) => UploadedFile::fake()->image("foto-{$i}.jpg"))
        ->all();

    $this->actingAs($mentor)
        ->from('/dooplab')
        ->post(route('dooplab.logbooks.entries.store', $logbook->uuid), [
            'activity_date' => '2026-06-30',
            'activity_time' => '09:00',
            'activity' => 'Dokumentasi terlalu banyak',
            'documentation' => $files,
        ])
        ->assertRedirect('/dooplab')
        ->assertSessionHasErrors('documentation');
});
