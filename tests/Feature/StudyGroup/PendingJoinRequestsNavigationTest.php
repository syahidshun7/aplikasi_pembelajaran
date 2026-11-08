<?php

use App\Models\JobRole;
use App\Models\StudyGroup;
use App\Models\StudyGroupJoinRequest;
use App\Models\StudyGroupStaff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('pending study groups view lists only classes with actionable join requests', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = User::factory()->create(['role' => User::ROLE_USER]);
    $job = JobRole::query()->create(['name' => 'Web', 'slug' => 'web']);

    $pendingGroup = StudyGroup::query()->create([
        'name' => 'Web A', 'invite_code' => 'WEB-A', 'max_members' => 20, 'job_id' => $job->id,
    ]);
    $otherGroup = StudyGroup::query()->create([
        'name' => 'Web B', 'invite_code' => 'WEB-B', 'max_members' => 20, 'job_id' => $job->id,
    ]);
    StudyGroupJoinRequest::query()->create([
        'study_group_id' => $pendingGroup->id, 'user_id' => $student->id, 'status' => 'pending',
    ]);
    StudyGroupJoinRequest::query()->create([
        'study_group_id' => $otherGroup->id, 'user_id' => $student->id, 'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->get(route('redesign.study-groups', ['view' => 'pending']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Redesign/AdminDashboard/StudyGroups', false)
            ->where('filters.view', 'pending')
            ->has('groups.data', 1)
            ->where('groups.data.0.uuid', $pendingGroup->uuid)
            ->where('groups.data.0.pending_requests_count', 1)
            ->etc());

    $this->actingAs($admin)
        ->get(route('redesign.study-groups.membership', $pendingGroup->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Redesign/AdminDashboard/Membership', false)
            ->has('requests', 1)
            ->etc());
});

test('mentor sees pending requests only for assigned classes', function () {
    $mentor = User::factory()->create(['role' => User::ROLE_MENTOR]);
    $student = User::factory()->create(['role' => User::ROLE_USER]);
    $job = JobRole::query()->create(['name' => 'Backend', 'slug' => 'backend']);

    foreach (['Assigned', 'Other'] as $name) {
        $group = StudyGroup::query()->create([
            'name' => $name, 'invite_code' => strtoupper($name), 'max_members' => 20, 'job_id' => $job->id,
        ]);
        StudyGroupJoinRequest::query()->create([
            'study_group_id' => $group->id, 'user_id' => $student->id, 'status' => 'pending',
        ]);
        if ($name === 'Assigned') {
            $assignedGroup = $group;
            StudyGroupStaff::query()->create([
                'study_group_id' => $group->id, 'user_id' => $mentor->id, 'role_in_group' => 'mentor',
            ]);
        }
    }

    $this->actingAs($mentor)
        ->get(route('redesign.study-groups', ['view' => 'pending']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups.data', 1)
            ->where('groups.data.0.uuid', $assignedGroup->uuid)
            ->etc());

    $this->actingAs($mentor)
        ->get(route('redesign.study-groups.membership', $assignedGroup->uuid))
        ->assertOk();
});
