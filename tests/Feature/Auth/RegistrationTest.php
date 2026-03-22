<?php

use App\Models\JobRole;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $job = JobRole::query()->create([
        'name' => 'Backend Engineer',
        'slug' => 'backend-engineer',
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'job_id' => $job->id,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('lobby', absolute: false));
});
