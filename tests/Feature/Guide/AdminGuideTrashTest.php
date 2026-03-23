<?php

use App\Models\Guide;
use App\Models\User;

it('admin can restore and hard delete trashed guide', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $guide = Guide::query()->create([
        'title' => 'Trash Guide',
        'description' => 'Guide for trash flow testing',
    ]);

    $this->actingAs($admin)
        ->delete(route('materi.destroy', $guide->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('guides', ['id' => $guide->id]);

    $this->actingAs($admin)
        ->patch(route('materi.restore', $guide->uuid))
        ->assertRedirect();

    $this->assertDatabaseHas('guides', ['id' => $guide->id, 'deleted_at' => null]);

    $this->actingAs($admin)
        ->delete(route('materi.destroy', $guide->uuid))
        ->assertRedirect();

    $this->assertSoftDeleted('guides', ['id' => $guide->id]);

    $this->actingAs($admin)
        ->delete(route('materi.force-destroy', $guide->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('guides', ['id' => $guide->id]);
});
