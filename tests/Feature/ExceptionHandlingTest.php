<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

test('web validation errors stay as redirects with session errors instead of becoming 500', function () {
    Config::set('app.debug', false);

    $user = User::factory()->create();

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('json validation errors return 422 instead of 500', function () {
    Config::set('app.debug', false);

    $user = User::factory()->create();

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

test('unauthenticated web requests redirect to login instead of rendering 500', function () {
    Config::set('app.debug', false);

    $response = $this->get('/profile');

    $response->assertRedirect(route('login'));
});

test('unauthenticated json requests return 401 instead of 500', function () {
    Config::set('app.debug', false);

    $response = $this->getJson('/profile');

    $response->assertStatus(401);
});

test('missing route model bindings render 404 instead of 500', function () {
    Config::set('app.debug', false);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/quests/999999');

    $response->assertNotFound();
});

test('system exceptions still fall back to the global 500 page', function () {
    Config::set('app.debug', false);

    $response = $this->get('/simulate-500');

    $response->assertStatus(500);
    $response->assertSee('SYSTEM_FAILURE');
});
