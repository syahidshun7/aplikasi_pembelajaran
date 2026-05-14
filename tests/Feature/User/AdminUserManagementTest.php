<?php

use App\Models\User;
use App\Services\LevelingService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

test('admin can update full user data from user management', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $user = User::factory()->create([
        'name' => 'Old Name',
        'username' => 'old_username_test',
        'email' => 'old-user@example.com',
        'role' => 'user',
        'gold' => 10,
        'exp' => 20,
    ]);

    if (Schema::hasColumn('users', 'level')) {
        $user->forceFill(['level' => 1])->save();
    }
    if (Schema::hasColumn('users', 'lvl')) {
        $user->forceFill(['lvl' => 1])->save();
    }

    $response = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.update', $user->id), [
            'name' => 'Updated Name',
            'username' => 'updated_username_test',
            'email' => 'updated-user@example.com',
            'role' => 'student',
            'gold' => 5000,
            'exp' => 8000,
            'level' => 9,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

    $response->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->username)->toBe('updated_username_test');
    expect($user->email)->toBe('updated-user@example.com');
    expect($user->role)->toBe('student');
    expect((int) $user->gold)->toBe(5000);
    expect((int) $user->exp)->toBe(8000);
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();

    $expectedLevel = LevelingService::levelFromExp(8000);

    if (Schema::hasColumn('users', 'level')) {
        expect((int) $user->level)->toBe($expectedLevel);
    }
    if (Schema::hasColumn('users', 'lvl')) {
        expect((int) $user->lvl)->toBe($expectedLevel);
    }
});

test('admin can delete another user account but cannot delete self', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create();

    $deleteResponse = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $target->id));

    $deleteResponse->assertRedirect(route('admin.users.index'));
    $this->assertSoftDeleted('users', ['id' => $target->id]);

    $selfDeleteResponse = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $admin->id));

    $selfDeleteResponse->assertRedirect(route('admin.users.index'));
    $selfDeleteResponse->assertSessionHasErrors('user');
    $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
});

test('admin can restore and hard delete a trashed user', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target->id))
        ->assertRedirect();

    $this->assertSoftDeleted('users', ['id' => $target->id]);

    $this->actingAs($admin)
        ->patch(route('admin.users.restore', $target->id))
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target->id))
        ->assertRedirect();

    $this->assertSoftDeleted('users', ['id' => $target->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.force-destroy', $target->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});
