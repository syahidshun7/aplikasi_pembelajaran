<?php

use App\Models\Guide;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('archived material can be permanently deleted', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $guide = Guide::create(['title' => 'Materi arsip', 'description' => 'Uji']);
    $guide->delete();

    $this->actingAs($admin)->get(route('redesign.materi', ['view' => 'trash']))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Redesign/AdminDashboard/GuideLibrary', false)
            ->where('materi.data.0.uuid', $guide->uuid)->etc());
    $this->delete(route('materi.force-destroy', $guide->uuid))->assertRedirect();
    expect(Guide::withTrashed()->find($guide->id))->toBeNull();
});
