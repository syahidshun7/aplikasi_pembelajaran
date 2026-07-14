<?php

use App\Models\DoopLabRoadmap;
use App\Models\DoopLabRoadmapEnrollment;
use App\Models\DoopLabRoadmapNode;
use App\Models\DoopLabRoadmapNodeProgress;
use App\Models\DoopLabRoadmapSection;
use App\Models\JobRole;
use App\Models\StudyGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('approved study group member can open group detail and see mentors', function () {
    $job = JobRole::query()->create([
        'name' => 'Frontend Engineer',
        'slug' => 'frontend-engineer',
    ]);

    $member = User::factory()->create([
        'role' => User::ROLE_USER,
        'job_id' => $job->id,
    ]);

    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
        'job_id' => $job->id,
    ]);

    $group = StudyGroup::query()->create([
        'name' => 'Frontend Party',
        'description' => 'Kelas khusus frontend modern.',
        'invite_code' => 'GRP-FE-DETAIL',
        'max_members' => 10,
        'min_level' => 1,
        'job_id' => $job->id,
    ]);

    $group->users()->attach($member->id, ['role' => 'member']);
    $group->users()->attach($mentor->id, ['role' => 'mentor']);

    $response = $this->actingAs($member)->get(route('groups.show', $group->uuid));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudyGroups/Show')
            ->where('group.name', 'Frontend Party')
            ->where('group.description', 'Kelas khusus frontend modern.')
            ->has('mentors', 1)
            ->where('mentors.0.name', $mentor->name)
        );
});

test('user cannot open study group detail before approved as member', function () {
    $member = User::factory()->create(['role' => User::ROLE_USER]);
    $outsider = User::factory()->create(['role' => User::ROLE_USER]);

    $group = StudyGroup::query()->create([
        'name' => 'Private Party',
        'description' => 'Only approved members.',
        'invite_code' => 'GRP-PRIVATE-1',
        'max_members' => 10,
        'min_level' => 1,
    ]);

    $group->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($outsider)->get(route('groups.show', $group->uuid));

    $response->assertForbidden();
});

test('approved study group member can view attached roadmap as class curriculum without enrollment progress', function () {
    $member = User::factory()->create(['role' => User::ROLE_USER]);
    $mentor = User::factory()->create(['role' => User::ROLE_MENTOR]);

    $group = StudyGroup::query()->create([
        'name' => 'Roadmap Party',
        'description' => 'Class with roadmap.',
        'invite_code' => 'GRP-ROADMAP',
        'max_members' => 10,
        'min_level' => 1,
    ]);

    $group->users()->attach($member->id, ['role' => 'member']);

    $roadmap = DoopLabRoadmap::query()->create([
        'title' => 'Laravel Class Path',
        'description' => 'View-only class curriculum.',
        'is_published' => true,
        'created_by_user_id' => $mentor->id,
    ]);

    $section = DoopLabRoadmapSection::query()->create([
        'roadmap_id' => $roadmap->id,
        'title' => 'Basics',
        'x' => 10,
        'y' => 10,
        'width' => 300,
        'height' => 180,
        'bg_color' => '#1e293b',
        'text_color' => '#e2e8f0',
        'sort_order' => 1,
    ]);

    DoopLabRoadmapNode::query()->create([
        'roadmap_id' => $roadmap->id,
        'section_id' => $section->id,
        'title' => 'Install Laravel',
        'x' => 40,
        'y' => 60,
        'width' => 180,
        'height' => 90,
        'bg_color' => '#38bdf8',
        'text_color' => '#082f49',
        'sort_order' => 1,
    ]);

    $group->roadmaps()->attach($roadmap->id, [
        'assigned_by_user_id' => $mentor->id,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($member)->get(route('groups.show', $group->uuid));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudyGroups/Show')
            ->has('classRoadmaps', 1)
            ->where('classRoadmaps.0.title', 'Laravel Class Path')
            ->where('classRoadmaps.0.nodes.0.title', 'Install Laravel')
        );

    expect(DoopLabRoadmapEnrollment::query()->count())->toBe(0);
    expect(DoopLabRoadmapNodeProgress::query()->count())->toBe(0);
});
