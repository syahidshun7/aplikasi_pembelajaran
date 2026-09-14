<?php

use App\Models\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Str;

it('admin can restore and hard delete trashed event', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $event = Event::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Trash Event',
        'description' => 'Event for trash flow testing',
        'sequence_order' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.events.destroy', $event->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('events', ['id' => $event->id]);

    $this->actingAs($admin)
        ->patch(route('admin.events.restore', $event->uuid))
        ->assertRedirect();

    $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);

    $this->actingAs($admin)
        ->delete(route('admin.events.destroy', $event->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('events', ['id' => $event->id]);

    $this->actingAs($admin)
        ->delete(route('admin.events.force-destroy', $event->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

it('shows newest admin events first', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    Event::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Older Event',
        'description' => 'Older event for sorting test',
        'sequence_order' => 99,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    Event::query()->create([
        'uuid' => (string) Str::uuid(),
        'title' => 'Newest Event',
        'description' => 'Newest event for sorting test',
        'sequence_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Admin/Index')
            ->where('events.data.0.title', 'Newest Event')
            ->where('events.data.1.title', 'Older Event')
        );
});
