<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\StudyGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin study group detail includes attendance dashboard matrix', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);

    $firstStudent = User::factory()->create([
        'role' => User::ROLE_USER,
        'name' => 'Andi Learner',
    ]);

    $secondStudent = User::factory()->create([
        'role' => User::ROLE_USER,
        'name' => 'Budi Learner',
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $group = StudyGroup::query()->create([
        'name' => 'Attendance Party',
        'description' => 'Attendance dashboard class.',
        'invite_code' => 'ATTEND-DASH',
        'max_members' => 20,
        'min_level' => 1,
    ]);

    $group->users()->attach($firstStudent->id, ['role' => 'member']);
    $group->users()->attach($secondStudent->id, ['role' => 'member']);
    $group->users()->attach($mentor->id, ['role' => 'mentor']);

    $firstEvent = Event::query()->create([
        'title' => 'Session 01',
        'description' => 'First class',
        'sequence_order' => 1,
        'study_group_id' => $group->id,
        'starts_at' => now()->subDays(2),
    ]);

    $secondEvent = Event::query()->create([
        'title' => 'Session 02',
        'description' => 'Second class',
        'sequence_order' => 2,
        'study_group_id' => $group->id,
        'starts_at' => now()->subDay(),
    ]);

    EventAttendance::query()->create([
        'event_id' => $firstEvent->id,
        'user_id' => $firstStudent->id,
        'status' => 'present',
        'checked_at' => now()->subDays(2),
    ]);

    EventAttendance::query()->create([
        'event_id' => $secondEvent->id,
        'user_id' => $firstStudent->id,
        'status' => 'absent',
        'checked_at' => now()->subDay(),
    ]);

    EventAttendance::query()->create([
        'event_id' => $firstEvent->id,
        'user_id' => $secondStudent->id,
        'status' => 'present',
        'checked_at' => now()->subDays(2),
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('groups.detail', $group->uuid));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudyGroups/Admin/Detail')
            ->where('attendanceDashboard.summary.total_events', 2)
            ->where('attendanceDashboard.summary.total_students', 2)
            ->where('attendanceDashboard.summary.class_attendance_rate', 50)
            ->has('attendanceDashboard.events', 2)
            ->has('attendanceDashboard.students', 2)
            ->where('attendanceDashboard.students.0.name', 'Andi Learner')
            ->where('attendanceDashboard.students.0.attendance_rate', 50)
            ->where('attendanceDashboard.students.0.events.0.status', 'present')
            ->where('attendanceDashboard.students.0.events.1.status', 'absent')
            ->where('attendanceDashboard.students.1.name', 'Budi Learner')
            ->where('attendanceDashboard.students.1.attendance_rate', 50)
            ->where('attendanceDashboard.students.1.events.1.status', 'pending')
        );
});
