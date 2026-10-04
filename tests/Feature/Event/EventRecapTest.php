<?php

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\StudyGroup;
use App\Models\User;

it('exports only selected group events and counts unrecorded attendance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $group = StudyGroup::create(['name' => 'Kelas A', 'invite_code' => 'RECAP-A']);
    $other = StudyGroup::create(['name' => 'Kelas B', 'invite_code' => 'RECAP-B']);
    $students = User::factory()->count(3)->create(['role' => 'user']);
    foreach ($students as $student) {
        $group->attachOrRestoreMember($student->id);
    }
    $group->attachOrRestoreMember($admin->id);
    $description = "Materi, diskusi dan latihan \"praktik\".\nEvaluasi bersama.";
    $event = Event::create(['title' => 'Event A', 'description' => 'Materi, diskusi dan latihan &quot;praktik&quot;.<br><strong>Evaluasi bersama.</strong>', 'study_group_id' => $group->id]);
    Event::create(['title' => 'Event B', 'study_group_id' => $other->id]);
    EventAttendance::create(['event_id' => $event->id, 'user_id' => $students[0]->id, 'status' => 'present']);
    EventAttendance::create(['event_id' => $event->id, 'user_id' => $students[1]->id, 'status' => 'excused']);
    EventAttendance::create(['event_id' => $event->id, 'user_id' => $admin->id, 'status' => 'present']);

    $response = $this->actingAs($admin)->get(route('groups.events.recap', $group->uuid))->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('Kelas A')->not->toContain('Event B');
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $header = fgetcsv($stream, 0, ',', '"', '');
    $row = fgetcsv($stream, 0, ',', '"', '');
    fclose($stream);
    expect($header[2])->toBe('Deskripsi');
    expect($row)->toBe(['Kelas A', 'Event A', $description, '', '', '', '', '', '', 'Belum tersedia', config('app.timezone'), '3', '1', '0', '1', '0', '1', '33.3%']);
});

it('exports Indonesian schedule dates and duration across midnight', function () {
    config(['app.timezone' => 'Asia/Makassar']);
    $admin = User::factory()->create(['role' => 'admin']);
    $group = StudyGroup::create(['name' => 'Kelas Jadwal', 'invite_code' => 'RECAP-SCHEDULE']);
    Event::create([
        'title' => 'Event Malam',
        'study_group_id' => $group->id,
        'starts_at' => '2026-10-05 23:30:00',
        'ends_at' => '2026-10-06 01:15:00',
    ]);
    Event::create([
        'title' => 'Event Tanpa Selesai',
        'study_group_id' => $group->id,
        'starts_at' => '2026-10-05 09:00:00',
    ]);

    $csv = $this->actingAs($admin)->get(route('groups.events.recap', $group->uuid))
        ->assertOk()->streamedContent();
    $lines = preg_split('/\r?\n/', trim(substr($csv, 3)));
    $row = str_getcsv($lines[1], ',', '"', '');
    expect($row[2])->toBe('');
    expect(array_slice($row, 3, 8))->toBe(['Senin', '05-10-2026', '23:30', 'Selasa', '06-10-2026', '01:15', '1 jam 45 menit', 'Asia/Makassar']);
    $incomplete = str_getcsv($lines[2], ',', '"', '');
    expect(array_slice($incomplete, 6, 4))->toBe(['', '', '', 'Belum tersedia']);
});

it('denies recap access to an unassigned mentor', function () {
    $mentor = User::factory()->create(['role' => 'mentor']);
    $group = StudyGroup::create(['name' => 'Private Group', 'invite_code' => 'RECAP-PRIVATE']);
    $this->actingAs($mentor)->get(route('groups.events.recap', $group->uuid))->assertForbidden();
});

it('returns not found for an unknown recap group', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('groups.events.recap', 'missing'))->assertNotFound();
});
