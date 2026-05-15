<?php

use App\Models\DoopLabRoadmap;
use App\Models\DoopLabRoadmapNode;
use App\Models\DoopLabRoadmapSection;
use App\Models\User;

it('mentor can access dooplab roadmap lab index', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $response = $this->actingAs($mentor)->get(route('dooplab.roadmaps.index'));

    $response->assertOk();
});

it('student cannot access mentor roadmap lab index', function () {
    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
    ]);

    $response = $this->actingAs($student)->get(route('dooplab.roadmaps.index'));

    $response->assertForbidden();
});

it('mentor can create roadmap with section node and edge', function () {
    $mentor = User::factory()->create([
        'role' => User::ROLE_MENTOR,
    ]);

    $this->actingAs($mentor)
        ->post(route('dooplab.roadmaps.store'), [
            'title' => 'Python Learning Path',
            'description' => 'Roadmap visual mentor',
            'is_published' => true,
        ])
        ->assertRedirect();

    $roadmap = DoopLabRoadmap::query()->where('title', 'Python Learning Path')->first();

    expect($roadmap)->not->toBeNull();

    $this->actingAs($mentor)
        ->post(route('dooplab.roadmaps.sections.store', $roadmap->uuid), [
            'title' => 'Python',
            'x' => 40,
            'y' => 40,
            'width' => 500,
            'height' => 260,
        ])
        ->assertRedirect();

    $section = DoopLabRoadmapSection::query()->where('roadmap_id', $roadmap->id)->first();
    expect($section)->not->toBeNull();

    $this->actingAs($mentor)
        ->post(route('dooplab.roadmaps.nodes.store', $roadmap->uuid), [
            'title' => 'Introduction',
            'section_uuid' => $section->uuid,
            'x' => 70,
            'y' => 120,
        ])
        ->assertRedirect();

    $this->actingAs($mentor)
        ->post(route('dooplab.roadmaps.nodes.store', $roadmap->uuid), [
            'title' => 'Data Manipulation',
            'section_uuid' => $section->uuid,
            'x' => 240,
            'y' => 180,
        ])
        ->assertRedirect();

    $nodes = DoopLabRoadmapNode::query()->where('roadmap_id', $roadmap->id)->orderBy('id')->get();

    expect($nodes)->toHaveCount(2);

    $this->actingAs($mentor)
        ->post(route('dooplab.roadmaps.edges.store', $roadmap->uuid), [
            'from_node_uuid' => $nodes[0]->uuid,
            'to_node_uuid' => $nodes[1]->uuid,
            'curvature' => 0.4,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('dooplab_roadmap_edges', [
        'roadmap_id' => $roadmap->id,
        'from_node_id' => $nodes[0]->id,
        'to_node_id' => $nodes[1]->id,
    ]);
});

