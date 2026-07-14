<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventCheckInCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can generate event check in code and user can attend with the code', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'Validated Attendance',
        'description' => 'Attendance with generated code',
        'sequence_order' => 1,
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addHour(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.events.attendance.check-in-code.generate', $event->uuid), [
            'duration_minutes' => 10,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('message', 'EVENT_CHECK_IN_CODE_GENERATED')
        ->assertSessionHas('check_in_code');

    $payload = session('check_in_code');

    expect((string) ($payload['code'] ?? ''))->toMatch('/^\d{6}$/');

    $storedCode = EventCheckInCode::query()->where('event_id', $event->id)->firstOrFail();
    expect(Hash::check((string) $payload['code'], (string) $storedCode->code_hash))->toBeTrue();
    expect((string) $storedCode->code_hash)->not->toBe((string) $payload['code']);

    $attendResponse = $this
        ->actingAs($user)
        ->post(route('events.attendance.code', $event->uuid), [
            'code' => $payload['code'],
        ]);

    $attendResponse
        ->assertRedirect()
        ->assertSessionHas('message', 'EVENT_CHECK_IN_ATTENDANCE_RECORDED');

    $attendance = EventAttendance::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect((string) $attendance->status)->toBe('present');
    expect($attendance->checked_at)->not->toBeNull();
});

test('expired event check in code cannot be used', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'Expired Attendance',
        'description' => 'Attendance with expired code',
        'sequence_order' => 1,
    ]);

    EventCheckInCode::query()->create([
        'event_id' => $event->id,
        'code_hash' => Hash::make('123456'),
        'plain_code_last_four' => '3456',
        'qr_token' => 'expired-token-for-test',
        'expires_at' => now()->subMinute(),
        'created_by_user_id' => null,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('events.show', $event->uuid))
        ->post(route('events.attendance.code', $event->uuid), [
            'code' => '123456',
        ]);

    $response
        ->assertRedirect(route('events.show', $event->uuid))
        ->assertSessionHasErrors('code');

    expect(EventAttendance::query()->where('event_id', $event->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

test('qr check in link records attendance instantly without manual pin entry', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $event = Event::query()->create([
        'title' => 'QR Attendance',
        'description' => 'Instant QR attendance',
        'sequence_order' => 1,
    ]);

    $checkInCode = EventCheckInCode::query()->create([
        'event_id' => $event->id,
        'code_hash' => Hash::make('654321'),
        'plain_code_last_four' => '4321',
        'qr_token' => 'valid-qr-token-for-test',
        'expires_at' => now()->addMinutes(10),
        'created_by_user_id' => null,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('events.attendance.qr', [
            'event' => $event->uuid,
            'token' => $checkInCode->qr_token,
        ]));

    $response
        ->assertRedirect(route('events.show', $event->uuid))
        ->assertSessionHas('message', 'EVENT_QR_ATTENDANCE_RECORDED');

    $attendance = EventAttendance::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect((string) $attendance->status)->toBe('present');
    expect($attendance->checked_at)->not->toBeNull();
});
