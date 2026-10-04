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
    $event->forceFill(['created_at' => '2026-10-05 11:30:00'])->save();
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
    expect($header)->toBe(['Waktu Mulai', 'Waktu Berakhir', 'Kelas', 'Nama Event', 'Deskripsi', 'Durasi', 'Total Siswa', 'Hadir', 'Tidak Hadir', 'Izin', 'Sakit', 'Belum Cek', 'Persentase Kehadiran', 'Created At']);
    expect($row)->toBe(['', '', 'Kelas A', 'Event A', $description, 'Belum tersedia', '3', '1', '0', '1', '0', '1', '33.3%', 'Senin, 05/10/2026 11.30 am']);
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
    $row = str_getcsv($lines[2], ',', '"', '');
    expect(array_slice($row, 0, 13))->toBe(['Senin, 05/10/2026 11.30 pm', 'Selasa, 06/10/2026 01.15 am', 'Kelas Jadwal', 'Event Malam', '', '1 jam 45 menit', '0', '0', '0', '0', '0', '0', '0%']);
    $incomplete = str_getcsv($lines[1], ',', '"', '');
    expect($incomplete[0])->toBe('Senin, 05/10/2026 09.00 am');
    expect($incomplete[1])->toBe('');
    expect($incomplete[5])->toBe('Belum tersedia');
});

it('orders recap by start date and time ascending with unscheduled events last', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $group = StudyGroup::create(['name' => 'Kelas Urutan', 'invite_code' => 'RECAP-ORDER']);
    foreach ([
        ['Terbaru', '2026-10-06 08:00:00', 1],
        ['Belum Dijadwalkan', null, 2],
        ['Siang', '2026-10-05 13:00:00', 3],
        ['Pagi', '2026-10-05 09:00:00', 4],
        ['Terlama', '2026-09-29 11:30:00', 5],
    ] as [$title, $startsAt, $sequence]) {
        Event::create(['title' => $title, 'study_group_id' => $group->id, 'starts_at' => $startsAt, 'sequence_order' => $sequence]);
    }
    $csv = $this->actingAs($admin)->get(route('groups.events.recap', $group->uuid))
        ->assertOk()->streamedContent();
    $lines = preg_split('/\r?\n/', trim(substr($csv, 3)));
    $titles = array_map(fn ($line) => str_getcsv($line, ',', '"', '')[3], array_slice($lines, 1));
    expect($titles)->toBe(['Terlama', 'Pagi', 'Siang', 'Terbaru', 'Belum Dijadwalkan']);
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
