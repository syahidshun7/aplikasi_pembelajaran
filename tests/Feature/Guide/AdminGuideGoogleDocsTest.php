<?php

use App\Models\Guide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('admin can create guide with google docs embed source', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->post(route('materi.store'), [
            'title' => 'Guide Google Docs',
            'description' => 'Panduan dari Google Docs',
            'content_source' => 'google_docs',
            'google_docs_url' => 'https://docs.google.com/document/d/abc123/edit?usp=sharing',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('guides', [
        'title' => 'Guide Google Docs',
        'file_path' => null,
        'google_docs_embed_url' => 'https://docs.google.com/document/d/abc123/preview',
    ]);
});

it('admin can switch guide resource from file to google docs embed', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $oldFilePath = UploadedFile::fake()->create('old-guide.pdf', 120, 'application/pdf')
        ->store('guides', 'public');

    $guide = Guide::query()->create([
        'title' => 'Switch Source Guide',
        'description' => 'Before update',
        'file_path' => $oldFilePath,
    ]);

    $this->actingAs($admin)
        ->post(route('materi.update', $guide->uuid), [
            'title' => 'Switch Source Guide',
            'description' => 'After update',
            'content_source' => 'google_docs',
            'google_docs_url' => 'https://drive.google.com/file/d/FILE-999/view?usp=sharing',
        ])
        ->assertRedirect();

    $guide->refresh();

    expect($guide->file_path)->toBeNull()
        ->and($guide->google_docs_embed_url)->toBe('https://drive.google.com/file/d/FILE-999/preview');

    Storage::disk('public')->assertMissing($oldFilePath);
});

