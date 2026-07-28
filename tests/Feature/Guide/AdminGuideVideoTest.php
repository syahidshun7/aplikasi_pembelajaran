<?php

use App\Models\Guide;
use App\Models\User;

it('creates a guide with a normalized youtube video source', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('materi.store'), [
            'title' => 'YouTube Video Guide',
            'content_source' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=abc123XYZ_9',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('guides', [
        'title' => 'YouTube Video Guide',
        'file_path' => null,
        'google_docs_embed_url' => null,
        'video_embed_url' => 'https://www.youtube-nocookie.com/embed/abc123XYZ_9',
    ]);
});

it('creates a guide with a google drive video source', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('materi.store'), [
            'title' => 'Drive Video Guide',
            'content_source' => 'video',
            'video_url' => 'https://drive.google.com/file/d/VIDEO-FILE-123/view?usp=sharing',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('guides', [
        'title' => 'Drive Video Guide',
        'video_embed_url' => 'https://drive.google.com/file/d/VIDEO-FILE-123/preview',
    ]);
});

it('rejects an unsupported video host', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('materi.index'))
        ->post(route('materi.store'), [
            'title' => 'Unsafe Video Guide',
            'content_source' => 'video',
            'video_url' => 'https://example.com/video/123',
        ])
        ->assertRedirect(route('materi.index'))
        ->assertSessionHasErrors('video_url');

    expect(Guide::query()->where('title', 'Unsafe Video Guide')->exists())->toBeFalse();
});
