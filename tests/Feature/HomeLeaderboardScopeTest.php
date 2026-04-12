<?php

use App\Models\JobRole;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('home leaderboard keeps global job scope and loads class leaderboard lazily per selected class', function () {
    $viewerJob = JobRole::query()->create([
        'name' => 'Teknologi Informasi',
        'slug' => 'teknologi-informasi',
    ]);

    $otherJob = JobRole::query()->create([
        'name' => 'Elektro',
        'slug' => 'elektro',
    ]);

    $viewer = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $viewerJob->id,
    ]);

    $iotClass = StudyGroup::query()->create([
        'name' => 'IOT',
        'description' => 'IoT class scope',
        'invite_code' => 'CLASS-IOT-001',
        'max_members' => 30,
        'job_id' => $viewerJob->id,
    ]);

    $pplgClass = StudyGroup::query()->create([
        'name' => 'XII PPLG',
        'description' => 'PPLG class scope',
        'invite_code' => 'CLASS-PPLG-001',
        'max_members' => 30,
        'job_id' => $viewerJob->id,
    ]);

    $otherJobClass = StudyGroup::query()->create([
        'name' => 'Outside Job Class',
        'description' => 'Different job class',
        'invite_code' => 'CLASS-OTHER-001',
        'max_members' => 30,
        'job_id' => $otherJob->id,
    ]);

    $iotMate = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $viewerJob->id,
    ]);

    $pplgMate = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $viewerJob->id,
    ]);

    $outsider = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $otherJob->id,
    ]);

    DB::table('users')->where('id', $viewer->id)->update(['level' => 9, 'exp' => 1000]);
    DB::table('users')->where('id', $iotMate->id)->update(['level' => 8, 'exp' => 2200]);
    DB::table('users')->where('id', $pplgMate->id)->update(['level' => 10, 'exp' => 3300]);
    DB::table('users')->where('id', $outsider->id)->update(['level' => 11, 'exp' => 4400]);

    $viewer->studyGroups()->attach($iotClass->id, ['role' => 'member']);
    $viewer->studyGroups()->attach($pplgClass->id, ['role' => 'member']);
    $iotMate->studyGroups()->attach($iotClass->id, ['role' => 'member']);
    $pplgMate->studyGroups()->attach($pplgClass->id, ['role' => 'member']);
    $outsider->studyGroups()->attach($otherJobClass->id, ['role' => 'member']);

    $initialResponse = $this
        ->actingAs($viewer)
        ->get(route('lobby'));

    $initialResponse->assertOk();
    $initialProps = $initialResponse->inertiaProps();

    $initialGlobal = collect(data_get($initialProps, 'leaderboards.global', []));
    $initialClass = collect(data_get($initialProps, 'leaderboards.class', []));
    $initialClassGroups = collect(data_get($initialProps, 'leaderboardMeta.class_groups', []));

    expect((string) data_get($initialProps, 'leaderboardMeta.global_scope_label'))->toBe('Teknologi Informasi');
    expect((int) data_get($initialProps, 'leaderboardMeta.loaded_class_group_id', 0))->toBe(0);
    expect($initialClass->count())->toBe(0);
    expect($initialClassGroups->pluck('id')->map(fn ($id) => (int) $id)->all())->toContain($iotClass->id, $pplgClass->id);

    expect($initialGlobal->pluck('id')->map(fn ($id) => (int) $id)->all())->toContain($iotMate->id, $pplgMate->id);
    expect($initialGlobal->pluck('id')->map(fn ($id) => (int) $id)->all())->not->toContain($outsider->id);

    $classResponse = $this
        ->actingAs($viewer)
        ->get(route('lobby', ['leaderboard_class_group_id' => $pplgClass->id]));

    $classResponse->assertOk();
    $classProps = $classResponse->inertiaProps();
    $classLeaderboard = collect(data_get($classProps, 'leaderboards.class', []));

    expect((int) data_get($classProps, 'leaderboardMeta.loaded_class_group_id', 0))->toBe($pplgClass->id);
    expect((string) data_get($classProps, 'leaderboardMeta.class_scope_label'))->toBe('XII PPLG');
    expect($classLeaderboard->pluck('id')->map(fn ($id) => (int) $id)->all())->toContain($pplgMate->id);
    expect($classLeaderboard->pluck('id')->map(fn ($id) => (int) $id)->all())->not->toContain($iotMate->id);
    expect($classLeaderboard->pluck('id')->map(fn ($id) => (int) $id)->all())->not->toContain($outsider->id);
});
