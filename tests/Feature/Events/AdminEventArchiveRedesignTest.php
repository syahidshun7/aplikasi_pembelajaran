<?php

use App\Models\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('event can be archived and restored from redesign list', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $event = Event::create(['title' => 'Workshop uji arsip', 'description' => 'Uji']);

    $this->actingAs($admin)->delete(route('admin.events.destroy', $event->uuid))->assertRedirect();
    $this->get(route('redesign.events', ['view' => 'trash']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/Events', false)
            ->where('events.data.0.uuid', $event->uuid)->etc());
    $this->patch(route('admin.events.restore', $event->uuid))->assertRedirect();
    expect(Event::find($event->id))->not->toBeNull();
});
