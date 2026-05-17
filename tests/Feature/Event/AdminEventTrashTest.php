<?php

use App\Models\Event;
use App\Models\User;
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
