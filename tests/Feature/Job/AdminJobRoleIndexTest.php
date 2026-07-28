<?php

use App\Models\JobRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('shows every job to a super admin on the jobs registry', function () {
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $jobs = collect([
        ['name' => 'Job Registry Active', 'status' => JobRole::STATUS_ACTIVE],
        ['name' => 'Job Registry Draft', 'status' => JobRole::STATUS_DRAFT],
        ['name' => 'Job Registry Soon', 'status' => JobRole::STATUS_COMING_SOON],
    ])->map(fn (array $attributes) => JobRole::query()->create([
        ...$attributes,
        'slug' => str($attributes['name'])->slug(),
    ]));

    $response = $this->actingAs($superAdmin)
        ->get(route('admin.jobs.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Jobs/Admin/Index')
            ->has('jobItems', JobRole::query()->count())
            ->where('jobItems', function ($items) use ($jobs) {
                $ids = collect($items)->pluck('id');

                return $jobs->every(fn (JobRole $job) => $ids->contains($job->id));
            })
            ->where('jobs.total', JobRole::query()->count())
        );
});
